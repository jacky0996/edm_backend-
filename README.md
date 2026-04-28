# EDM Backend

Laravel 12 + PHP 8.2 打造的**電子郵件行銷 (EDM) 後端服務**。負責**會員 / 群組 / 活動管理**、**活動邀請信寄送**(AWS SES)、**Google Forms 問卷整合**,並作為 [Middle Platform](../Middle_Platform) SSO 的下游消費者(以 JWT 驗證身分)。

> ▸ **想直接跑起來** → 看 [docs/deployment.md](./docs/deployment.md)(`docker compose ... up -d --build` 一行)
> ▸ **想懂為什麼做** → 看 [docs/overview.md](./docs/overview.md)(系統定位 + Scope + Stakeholders)
> ▸ **想串接 API** → 看 [docs/api-spec.md](./docs/api-spec.md)(全部 endpoint + request/response 約定)
> ▸ **想了解架構** → 看 [docs/architecture.md](./docs/architecture.md)(分層 + Component / Class Diagram)
> ▸ **想理解資料表** → 看 [docs/data-model.md](./docs/data-model.md)(ERD)

技術棧:PHP 8.2 / Laravel 12 / MySQL 8.4 / Nginx / Docker Compose / Firebase JWT / AWS SES / Google API。

---

## SA 文件索引

本專案以 SA 視角整理文件,給三類讀者使用:

- **串接方(EDM 前端、其他服務)** — 想知道 API 怎麼呼叫
- **開發者 / SA / Architect** — 想知道系統長什麼樣、為什麼這樣設計
- **未來維護者** — 想知道某個技術決策當初的取捨

文件採 Markdown + Mermaid 撰寫,GitHub 直接渲染,不需要任何工具即可閱讀。

---

## 推薦閱讀順序

| # | 文件 | 給誰看 | 對應 UML 圖 |
|---|---|---|---|
| 1 | [docs/overview.md](./docs/overview.md) | 所有人 | — (純文字:動機 / Scope / Stakeholders) |
| 2 | [docs/architecture.md](./docs/architecture.md) | 開發者 / Architect | **Component Diagram** + **Class Diagram**(分層架構 + Eloquent 關聯) |
| 3 | [docs/data-model.md](./docs/data-model.md) | 開發者 / DBA | **ERD**(17 張表 + 欄位 + 索引) |
| 4 | [docs/api-spec.md](./docs/api-spec.md) | 串接方(EDM 前端) | — (HTTP contract,非 UML;補充 Scramble 自動產生的 Swagger UI) |
| 5 | [docs/sequence-diagrams.md](./docs/sequence-diagrams.md) | SA / 開發者 | **Sequence Diagram**(JWT 驗證、邀請信寄送、Google Form 同步) |
| 6 | [docs/deployment.md](./docs/deployment.md) | Ops / Architect | **Deployment Diagram**(Docker Compose × 3 layer) |
| 7 | [docs/adr/](./docs/adr/) | 後續維護者 | — (Architecture Decision Records) |

---

## 不同角色的入口建議

| 你是誰 | 從這裡開始 |
|---|---|
| **第一次來** | `docs/overview.md` → `docs/architecture.md` → `docs/sequence-diagrams.md` |
| **要 review 設計** | `docs/architecture.md` → `docs/data-model.md` → `docs/adr/` |
| **要串接 API** | `docs/api-spec.md` → Scramble Swagger UI(http://localhost:81/docs/api) |
| **要部署 / 維運** | `docs/deployment.md` |
| **要查 DB schema** | `docs/data-model.md` |
| **要查 JWT 驗證細節** | `docs/sequence-diagrams.md` 第 1 節 + `docs/adr/0001-jwt-shared-secret.md` |

---

## 文件公約

- **圖優於文字**:盡量用 Mermaid 畫圖,文字補關鍵說明
- **每份文件 < 5 分鐘看完**:超過就拆檔
- **變更 code 時同步更新**:文件與 code 同 repo,改 model 就改 `data-model.md`,改 endpoint 就改 `api-spec.md`
- **決策必留 ADR**:任何「為什麼選 A 不選 B」的判斷,新增一份 ADR(模板見 [`docs/adr/`](./docs/adr/))
- **與中台對齊**:本系統是 [Middle Platform](../Middle_Platform) 生態的一份子,SA 文件結構與術語(Actor / IdP / SP / ADR)刻意與中台一致,讓跨 repo 閱讀體驗連貫
