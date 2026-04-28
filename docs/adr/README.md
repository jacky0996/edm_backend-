# Architecture Decision Records (ADR)

本目錄收錄 EDM Backend 的關鍵架構決策。每份 ADR 回答一個「**為什麼選 A 不選 B**」的問題,讓未來維護者(包括未來的自己)在改動前能先理解當初的取捨。

## 索引

| # | 標題 | 狀態 | 影響範圍 |
|---|---|---|---|
| [0001](./0001-jwt-shared-secret.md) | JWT 採用共享 APP_KEY 本地驗證(HS256) | Accepted | 認證機制、跨系統耦合 |
| [0002](./0002-scramble-vs-l5swagger.md) | API 文件採用 Scramble 取代 L5-Swagger | Accepted | 開發體驗、文件維護成本 |
| [0003](./0003-soft-deletes.md) | 業務 entity 預設啟用 Soft Delete | Accepted | DB schema、查詢規則、稽核 |

> 模板與寫作公約見 [Middle Platform docs/adr/README.md](../../../Middle_Platform/docs/adr/README.md) — 兩個 repo 共用同一個 ADR 模板,讓 reviewer 跨 repo 閱讀時體驗一致。
