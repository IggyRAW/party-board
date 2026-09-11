# Party Board

Figma Make の [ゲームスコア集計アプリ](https://www.figma.com/make/frs7k3rorLwfiiz0fEGVp4) を、Laravel + Inertia + Vue で実装したパーティー向けスコアボードです。

- ゲーム・チーム・スコアの集計とランキング
- 景品ルーレット抽選（全画面表示対応）
- データは MySQL に永続化

## 必要環境

- Docker Desktop

## 起動

リポジトリ直下で次を実行します。

```bash
docker compose up --build
```

起動後:

- アプリ: http://localhost:8000
- Vite（HMR）: http://localhost:5174

初回起動時にマイグレーションと初期データ（クイズバトル / ビンゴ、サンプル景品・参加者）が投入されます。

## よく使うコマンド

```bash
# ログ
docker compose logs -f app

# Artisan
docker compose exec app php artisan migrate
docker compose exec app php artisan db:seed --force

# テスト（コンテナ内）
docker compose exec app php artisan test
```

ホストに PHP がある場合は `laravel/` 配下でもテストできます。

```bash
cd laravel
php artisan test
```

## 技術スタック

| 層 | 技術 |
| --- | --- |
| バックエンド | Laravel 13 / Inertia.js |
| フロントエンド | Vue 3 / Vite / Tailwind CSS 4 |
| DB | MySQL 8.4 |
| 実行環境 | Docker Compose |

## ディレクトリ

```
docker-compose.yml    # app / vite / db
docker/php/           # PHP コンテナ定義
laravel/              # Laravel アプリケーション
```
