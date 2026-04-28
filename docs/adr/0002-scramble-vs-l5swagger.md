# ADR-0002: API 文件採用 Scramble 取代 L5-Swagger

- **狀態**: Accepted
- **日期**: 2026-04-19
- **決策者**: Shane (SA / 開發者)

## Context — 我們在解決什麼問題?

EDM Backend 對前端提供 30+ 個 endpoint。前端開發者需要可以「打開瀏覽器就能看每個 endpoint 的 request / response schema 並試打」的工具。Laravel 生態有兩個主流方案:

1. **L5-Swagger** (`darkaonline/l5-swagger`):需要在每個 controller method 上方寫 `@OA\Get(...)` / `@OA\Post(...)` 等 annotations,工具掃描後產 OpenAPI YAML
2. **Scramble** (`dedoc/scramble`):從 PHPDoc + IDE-style type hints **自動推導** OpenAPI,基本不用寫額外註解

兩個都能產 Swagger UI,差別在「**需要額外維護多少 API 註解**」。

## Decision — 我們選了什麼?

**採用 `dedoc/scramble`(目前 ^0.13.20)**:

- 從 controller method 的 PHPDoc + return type + FormRequest 自動推導 OpenAPI 3.0 spec
- Swagger UI 在 http://localhost:81/docs/api(預設路徑)
- OpenAPI JSON 在 http://localhost:81/docs/api.json

> `composer.json` 顯示已採 Scramble。**README 早期版本提到 L5-Swagger 是過時資訊**,以實際 dependency 為準。

## Considered Options — 還評估過哪些?

### 選項 1 — L5-Swagger(annotation 派)

```php
/**
 * @OA\Post(
 *     path="/api/edm/event/create",
 *     @OA\RequestBody(required=true, @OA\JsonContent(
 *         required={"title","start_time"},
 *         @OA\Property(property="title", type="string"),
 *         @OA\Property(property="start_time", type="string", format="datetime"),
 *     )),
 *     @OA\Response(response=200, description="Success")
 * )
 */
public function create(Request $request) { ... }
```

- ✅ 業界知名度最高,生態最廣
- ✅ 完整控制 spec 細節(可寫複雜的 `oneOf` / `discriminator` 等)
- ❌ 每個 endpoint **多寫 10-30 行 annotation**,且容易跟實際 code 走鐘
- ❌ Annotation 本身不參與執行,常常 silently 過時

### 選項 2 — Scramble(自動推導派)【選中】

```php
/**
 * 建立活動
 * @param EventCreateRequest $request
 */
public function create(EventCreateRequest $request): EventResource
{
    return new EventResource(
        $this->repo->create($request->validated())
    );
}
```

- ✅ 寫 code 就等於寫 doc,不會走鐘
- ✅ FormRequest 的驗證規則自動變成 OpenAPI schema
- ✅ Resource class 的 `toArray()` 變成 response schema
- ⚠ 對複雜場景(polymorphic response、conditional fields)推導可能不準
- ⚠ 套件較新(0.x 版本),API 可能變動

### 選項 3 — 純手寫 OpenAPI YAML

- ✅ 完全控制
- ❌ 維護負擔最重,跟 code 完全脫節

### 選項 4 — 不寫 API 文件

- ✅ 零成本
- ❌ 前端要直接讀 PHP code 才知道參數,跨團隊溝通成本爆炸

## Consequences — 這個決定帶來什麼?

### ✅ 正面

- **零額外維護成本**:寫好 controller + FormRequest + Resource,文件就有了
- **不會走鐘**:文件直接從 code 推,改 code 就改文件
- **前端開發體驗好**:Swagger UI 即看即試

### ⚠ 負面 / Trade-off

- **複雜場景推導不準**:例如某 endpoint 同時可能回 `{"event": ...}` 或 `{"error": ...}`,Scramble 不一定能正確產 `oneOf`。緩解:用 PHPDoc `@response` 手動補
- **對開發習慣有要求**:必須**確實使用** FormRequest 與 Resource class,不然 Scramble 沒東西可推
- **0.x 版本**:Breaking change 風險。緩解:鎖定 minor version,升版前測 spec 是否還合理

### 🔁 後續追蹤

- 監控生成的 spec 是否準確,有偏差時回頭補 PHPDoc 或考慮升 1.x(若已 stable)
- 第二個業務後端加入時,確認 OpenAPI spec 可以匯出給共用 portal(對應 SA 文件治理計畫)

## References

- Code:
  - [`composer.json`](../../composer.json) — `dedoc/scramble: ^0.13.20`
  - 各 Controller / FormRequest / Resource 即為文件來源
- 文件:
  - [`docs/api-spec.md`](../api-spec.md)
- 外部:
  - [Scramble](https://scramble.dedoc.co/) — 官方文件
  - [L5-Swagger](https://github.com/DarkaOnLine/L5-Swagger) — 對照組
  - [OpenAPI 3.0 Specification](https://spec.openapis.org/oas/v3.0.3)
