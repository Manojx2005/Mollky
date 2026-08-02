<p align="center">
  <img src="images/logo.png" alt="MÖLKKY" width="280">
</p>

<h1 align="center">モルック大会アプリ ─ Mölkky Tournament Manager</h1>

<p align="center">
  <strong>チーム分け・手動チーム作成・フットボールトーナメント・得点自動計算・実況＆音声アナウンス・OBSライブ配信・印刷＆CSV出力まで、すべてをワンクリックで。</strong><br>
  <sub>A complete Mölkky tournament management system for Windows PC — zero install required.</sub>
</p>

<p align="center">
  <img src="https://img.shields.io/badge/PHP-8.x-777BB4?logo=php&logoColor=white" alt="PHP 8">
  <img src="https://img.shields.io/badge/SQLite-3-003B57?logo=sqlite&logoColor=white" alt="SQLite 3">
  <img src="https://img.shields.io/badge/Platform-Windows%20%7C%20macOS%20%7C%20Linux-blue" alt="Platform">
  <img src="https://img.shields.io/badge/Tournament-Knockout%20%26%20League-gold" alt="Tournament Ready">
  <img src="https://img.shields.io/badge/OBS-Overlay%20Ready-red?logo=obsstudio&logoColor=white" alt="OBS Ready">
</p>

---

## ✨ 主な特徴 / Main Features

| | 機能 | 詳細 |
|---|---|---|
| ⚽ | **フットボール方式 トーナメント表** | サッカー・カップ戦風のノックアウトツリーを自動生成。勝者自動進出、シード (BYE) 処理、3位決定戦、優勝者表彰バナー付き |
| 🔄 | **総当たり戦 (ラウンドロビン)** | 全チームが対戦するリーグ戦スケジューラ |
| 🎲 | **チーム自動シャッフル & 手動作成** | 1チーム 2〜8人での自動分け、または手動で名前とメンバーを自由選択 |
| 👥 | **チーム＆メンバー個別の詳細編集** | チーム名変更・メンバーの追加/外す/他チームへの移動・その場で新規生徒登録 |
| 📊 | **個人得点王ランキング & アナリティクス** | MVP・最高点・ミス数・チーム直近フォーム (W/L)・スキットル (1〜12) 命中頻度グラフ |
| 🏆 | **公式表彰状・賞状ジェネレーター** | 優勝・準優勝・3位の伝統的な表彰状をワンクリック生成＆印刷 |
| 📝 | **リアルタイム試合記録 & 実況ログ** | スキットル選択UI、得点・バースト・失格自動判定、実況タイムライン (Play-by-Play)、経過時間タイマー |
| 🔊 | **効果音 & 選手名音声実況** | Web Audio 効果音 + 音声合成アナウンス（「次は ○○ さんの番です」） |
| 🎨 | **3つのカラーテーマ切替** | Lawn Green（芝生）・Dark Mode（夜間）・Rustic Wood（木目調）を即座に切替 |
| 🖨️ | **印刷 & CSVデータ出力** | チーム一覧、対戦表、順位表、名簿、試合履歴の印刷＆Excel対応CSVエクスポート |
| 📺 | **OBS 配信オーバーレイ** | 透過スコアボードを動画配信にリアルタイム合成 |
| 💾 | **完全ポータブル設計** | PHP 同梱 + SQLite 1ファイル。USBメモリだけでどこでも起動 |

---

## 🚀 クイックスタート（30秒で起動）

### Windows（推奨）

```
1. フォルダ全体を任意の場所にコピー（または ZIP を展開）
2. start.bat をダブルクリック
3. ブラウザが自動で開きます → http://localhost:8000/home.php
4. 終了するときは黒いウィンドウで Ctrl + C
```

> [!TIP]
> **別の PC へ渡すとき**は、`php/` フォルダも含めて**丸ごと**コピーしてください。  
> 同梱の PHP が使われるため、相手の PC に PHP をインストールする必要はありません。

### macOS / Linux

```bash
# PHP と SQLite3 がインストール済みであることを確認
bash start.sh
```

---

## 🗺️ 画面一覧と構造 / Application Architecture

```
                               ┌─────────────────────────┐
                               │     メインナビゲーション  │
                               └────────────┬────────────┘
                                            │
   ┌─────────────┬─────────────┬────────────┼────────────┬─────────────┬─────────────┐
   ▼             ▼             ▼            ▼            ▼             ▼             ▼
 🏠 ホーム     📖 ルール    🏆 大会表     📊 順位表    📈 詳細統計   表彰状発行   ダッシュボード
(home.php)   (rules.php)  (tournament) (standings)   (stats.php) (certificates) (index.php)
                                                                                     │
                                    ┌───────────────────┬────────────────────────────┤
                                    ▼                   ▼                            ▼
                             🔍 参加者選択        ➕ 手動チーム作成           ✏️ チーム個別編集
                              (search.php)        (create_team.php)           (edit_team.php)
                                    │                   │                            │
                                    └───────────────────┴────────────────────────────┤
                                                                                     ▼
                                                                                🎮 試合スコア
                                                                                 (match.php)
                                                                                     │
                                                                                📺 OBS Overlay
                                                                                 (overlay.php)
```

### 全ファイル・機能一覧

| 画面 / 機能 | ファイル | 説明 |
|---|---|---|
| **ホーム** | `home.php` | モルックの紹介、月間ニュース、制作チームメンバー |
| **ルール解説** | `rules.php` | 用具・配置・投げ方・50点勝利・25点バースト・3回ミス失格の詳細ルール |
| **ダッシュボード** | `index.php` | 大会全体統計、チーム一覧、自動/手動チーム作成、印刷・CSV出力、直近試合結果 |
| **大会 (トーナメント表)** | `tournament.php` | **フットボール方式ノックアウトツリー**（自動勝者進出・3位決定戦付き） ＆ **総当たり戦** |
| **順位表 (リーダーボード)** | `standings.php` | 全試合結果を集計した勝敗・得失点差ランキング（🥇🥈🥉 メダル表示） |
| **詳細統計 & アナリティクス** | `stats.php` | **個人得点王ランキング (MVP)**・スキットル命中率チャート・チーム連勝ガイド |
| **表彰状発行** | `certificates.php` | 優勝・準優勝・3位チーム向けの印刷可能な公式表彰状 |
| **試合スコア記録** | `match.php` | ピン選択得点盤、ミス・バースト自動判定、Play-by-Play実況ログ、経過タイマー、音声アナウンス |
| **手動チーム作成** | `create_team.php` | チーム名を決めて、登録生徒から自由にメンバーを選んで作成 |
| **チーム詳細編集** | `edit_team.php` | チーム名変更・メンバーの追加/外し/チーム間移動・その場での新規生徒登録 |
| **試合履歴** | `history.php` | 全試合の時系列履歴、ステータス別フィルター |
| **CSVエクスポート** | `export.php` | チーム名簿、全試合結果、生徒名簿、順位表のCSVダウンロード（UTF-8 BOM対応） |
| **OBS 配信オーバーレイ** | `overlay.php` | 動画配信ソフト (OBS) 用透過ライブスコアボード（1秒間隔更新） |

---

## 🎯 モルックの公式ルール（自動適用）

| ルール | 内容 | アプリの動作 |
|--------|------|-------------|
| **得点計算** | 1本倒し → ピンの番号 / 複数本 → 倒した本数 | タップしたピンから得点を即時計算 |
| **勝利条件** | ちょうど **50点** で勝利 | 到達時に自動で試合終了＆勝者判定 |
| **バースト** | 50点を超えたら **25点** に減点 | 超過時に自動リセット |
| **連続ミス** | **3回連続ミス** で即失格（0点負け） | ミス追跡ドット表示 + 相手の勝利を自動判定 |

---

## 📺 OBS 配信スコアボードの設定

| 設定項目 | 値 |
|---------|-----|
| **ソースの種類** | ブラウザソース (Browser Source) |
| **URL** | `http://localhost:8000/overlay.php` |
| **デモ表示 (レイアウト調整用)** | `http://localhost:8000/overlay.php?demo=1` |

---

## 🎨 テーマ切り替え機能

画面右上の **🎨 テーマ** ボタンをクリックすると、いつでも以下のデザインテーマに切り替えられます：

1. 🌿 **Lawn Green** (標準スポーツグリーン)
2. 🌙 **Dark Mode** (モダンなダークテーマ)
3. 🪵 **Rustic Wood** (クラシックな木目調)

---

## 💾 データ管理とリセット

すべてのデータは **`molkky.sqlite`** という単一ファイルに保持されます。

* **データをリセットしたいとき**: `molkky.sqlite` を削除して `start.bat` を起動（`seed.sql` から初期生徒データが自動登録されます）。
* **バックアップや共有**: `molkky.sqlite` をファイルごと保存・移動するだけでバックアップ完了。

---

## 👥 プロジェクトチーム

| 部門 | 人数 | 役割 |
|------|------|------|
| 🎖️ **運営** | 3名 | リーダー、サブリーダー、ファシリテーター |
| 🎨 **デザイン** | 3名 | UI/UX デザイン、グラフィック制作 |
| 💻 **プログラム** | 6名 | バックエンド、フロントエンド、DB設計 |
| 🎬 **映像制作** | 5名 | 紹介動画、大会記録映像 |

---

## 🛠️ 技術スタック

* **バックエンド**: PHP 8.x（組み込み Web サーバー `php -S`）
* **データベース**: SQLite 3（`molkky.sqlite`）
* **フロントエンド**: Vanilla HTML5, Modern CSS (Variable/Themes), JavaScript (Web Audio API, Web Speech API)
* **リアルタイム配信**: Fetch API JSON ポーリング (1秒更新)

---

<p align="center">
  <sub>© 2026 MÖLKKY Tournament Manager — Built with ❤️ for Mölkky Players Worldwide</sub>
</p>