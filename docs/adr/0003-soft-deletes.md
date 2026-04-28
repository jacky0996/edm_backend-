# ADR-0003: 業務 Entity 預設啟用 Soft Delete

- **狀態**: Accepted
- **日期**: 2026-04-19
- **決策者**: Shane (SA / 開發者)

## Context — 我們在解決什麼問題?

EDM Backend 處理的是**有業務歷史價值的資料**:

- **Event(活動)**:辦過的活動是稽核紀錄,行銷成效報表會回看歷年活動
- **EventRelation(邀請關聯)**:誰在哪場活動被邀請、是否報到,是 KPI 統計的原始資料
- **Member(會員)**:離職員工經手過的會員不該消失,否則歷史報表斷鏈
- **GoogleForm + Responses**:報名紀錄是法遵與業務 SLA 的重要證據

如果用「真正的 DELETE」處理,有兩個問題:

1. **誤刪無法救回**:行銷人員 UI 操作失誤,點錯刪除按鈕,資料回不來
2. **稽核斷鏈**:reporting 看不到歷史資料,FK 對應的關聯紀錄孤立

技術上的兩個基本選項:

- **真刪除**(SQL DELETE)
- **軟刪除**(`deleted_at` 欄位代替 DELETE,Eloquent `SoftDeletes` trait)

## Decision — 我們選了什麼?

**所有業務 entity 預設啟用 Soft Delete**:

| Table | Soft Delete | 原因 |
|---|---|---|
| `member` | ✅ | 業務歷史 |
| `event` | ✅ | 業務歷史 + 報表 |
| `event_relation` | ✅ | KPI 來源 |
| `google_form` | ✅ | 報名紀錄 |
| `email` | ✅ | 寄信對象,稽核需要 |
| (組織、群組、表單回應) | ⚠ 視需要決定,目前未開 | 評估中 |

實作:Migration 加 `$table->softDeletes()`,Model `use SoftDeletes;` trait。

## Considered Options — 還評估過哪些?

### 選項 1 — 真刪除 (SQL DELETE)

- ✅ DB 簡單,不用記得加 `whereNull('deleted_at')`
- ✅ Storage 不會因為「假刪除」而膨脹
- ❌ 誤刪無救,需要從 backup 還原(慢、複雜、可能丟資料)
- ❌ FK 對應的關聯紀錄孤立(or 連帶刪除導致資料雪崩)
- ❌ 稽核找不到「誰在哪天刪了什麼」

### 選項 2 — Soft Delete (`deleted_at`) 【選中】

- ✅ 誤刪可救:`->restore()` 一行
- ✅ 稽核可追:`deleted_at` + `deleted_by`(若加)記錄完整
- ✅ Eloquent `SoftDeletes` trait **預設過濾掉**已刪除紀錄,業務 query 不用改
- ⚠ Storage 持續增長(正式是稽核需要,不是 bug)
- ⚠ Query 寫錯時會撈到已刪除紀錄(忘了用 Eloquent 方法、用 raw SQL)
- ⚠ 唯一索引可能跟「同名再建」衝突(已刪的還佔位)

### 選項 3 — Audit Log Table(完全分流)

- ✅ 業務表保持乾淨,刪除事件全進 audit log
- ❌ 需要寫一整套 audit infra
- ❌ 還原成本高(要從 audit 反推回業務狀態)
- 🔁 對作品集規模過度工程,但長期是可考慮方向

## Consequences — 這個決定帶來什麼?

### ✅ 正面

- **行銷人員的容錯空間**:UI 上「刪除」其實是 soft delete,後台還能還原
- **歷史報表完整**:離職員工的會員、停辦的活動,在 reporting 上仍可見
- **稽核**:配合 `created_at` / `updated_at` / `deleted_at`,完整生命週期可追蹤

### ⚠ 負面 / Trade-off

- **唯一索引衝突**:例如 `member.id_card_number` 若加 unique,刪除後同號再加會衝突。緩解:
  - 唯一索引改為複合 `(id_card_number, deleted_at)`
  - 或唯一索引條件式(部分索引,僅 `deleted_at IS NULL` 時生效)— MySQL 不支援,需用 generated column 繞

- **Query 出錯風險**:用 Eloquent 沒問題(自動過濾),但 raw SQL / DB::table 會撈到。緩解:
  - 文件規範:**raw query 必須顯式加 `WHERE deleted_at IS NULL`**
  - Code review 把關
  - 重要報表可寫 view 強制過濾

- **Storage 膨脹**:長期累積會變大。緩解:
  - 定期歸檔(超過 N 年的 soft-deleted 移到 archive table)
  - 重要表加 partition by year

### 🔁 後續追蹤

- 補 `deleted_by` 欄位(目前只有 `deleted_at`,不知道是誰刪的)
- 評估是否加「強制硬刪」的 admin tool(GDPR 「被遺忘權」場景需要真刪)
- 監控 `deleted_at IS NOT NULL` 的紀錄比率,過高代表 UX 設計有問題(使用者頻繁誤刪)

## References

- Code:
  - 各業務 Migration:[`database/migrations/`](../../database/migrations/) — 含 `softDeletes()` 的 schema
  - 各 Model:`use SoftDeletes;` trait
- 文件:
  - [`docs/data-model.md` 第 3.3 節](../data-model.md#33-soft-delete-預設開啟)
- 外部:
  - [Laravel Soft Deletes](https://laravel.com/docs/12.x/eloquent#soft-deleting)
  - [Martin Fowler — Soft Delete pattern discussion](https://martinfowler.com/bliki/) (尋常設計討論)
