# Oil Price Monitoring System

## 目前資料庫設定

專案目前已設定正確的 InfinityFree MySQL 資訊：

- Host: `sql301.infinityfree.com`
- Port: `3306`
- Database: `if0_38435166_goldshoot0720`
- Username: `if0_38435166`

## 執行方式

- 部署在 InfinityFree 主機時，系統會優先直接使用 MySQL。
- 在這台本機 Windows 如果 MySQL 主機無法解析或無法連線，系統會自動改用 `storage/oil_prices.sqlite`。
- 這代表正式站可用 MySQL，本機仍可持續測試，不會整站失效。

## 本機啟動

```bash
php -S 127.0.0.1:8080 -t public
```

## 自動抓取

- Windows 工作排程名稱：`OilPriceMonitor-Daily`
- 每日執行時間：`13:00` Asia/Taipei
- 排程執行檔：[run-oil-fetch.bat](D:/codes/codexs/phpoil/run-oil-fetch.bat)
- 抓價腳本：[cron/fetch_daily.php](D:/codes/codexs/phpoil/cron/fetch_daily.php)

## macOS 自動抓取

- macOS 執行檔：`run-oil-fetch.command`
- macOS 安裝腳本：`install-macos-launch-agent.sh`
- 預設每日執行時間：`13:00` Asia/Taipei
- 登入或開機後背景自動執行：已啟用 `RunAtLoad`

### macOS 手動執行

```bash
chmod +x run-oil-fetch.command
./run-oil-fetch.command
```

### macOS 安裝排程

```bash
chmod +x install-macos-launch-agent.sh
./install-macos-launch-agent.sh
```

若要改成其他時間，例如每天 `09:30`：

```bash
./install-macos-launch-agent.sh 9 30
```

### macOS 檢查排程

```bash
launchctl list | grep phpoil
tail -f storage/scheduled-fetch.log
```

### macOS 移除排程

```bash
launchctl unload ~/Library/LaunchAgents/com.goldshoot0720.phpoil.fetch.plist
rm ~/Library/LaunchAgents/com.goldshoot0720.phpoil.fetch.plist
```

## 手動驗證

```bash
php cron/fetch_daily.php
```

## Windows 排程建立

```powershell
powershell -ExecutionPolicy Bypass -File .\setup-scheduled-task.ps1
```

## InfinityFree 部署要上傳的檔案

請上傳這些項目：

- `config.php`
- `public/`
- `src/`
- `cron/`

可一起上傳但不是正式站必要：

- `README.md`

本機專用，不建議上傳：

- `storage/oil_prices.sqlite`
- `storage/scheduled-fetch.log`
- `run-oil-fetch.bat`
- `setup-scheduled-task.ps1`
