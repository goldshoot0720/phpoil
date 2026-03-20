$taskName = "OilPriceMonitor-Daily"
$scriptPath = "D:\codes\codexs\phpoil\run-oil-fetch.bat"
$startTime = "13:00"

if (-not (Test-Path $scriptPath)) {
    Write-Error "Task runner not found: $scriptPath"
    exit 1
}

$action = New-ScheduledTaskAction -Execute $scriptPath
$trigger = New-ScheduledTaskTrigger -Daily -At $startTime
$settings = New-ScheduledTaskSettingsSet -StartWhenAvailable -AllowStartIfOnBatteries -DontStopIfGoingOnBatteries

Register-ScheduledTask `
    -TaskName $taskName `
    -Action $action `
    -Trigger $trigger `
    -Settings $settings `
    -Description "Fetch OQD Daily Marker Price every day at 13:00" `
    -Force | Out-Null

Write-Output "Scheduled task '$taskName' has been created or updated."