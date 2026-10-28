@echo off
setlocal EnableExtensions

REM Este arquivo é uma cópia do original que fica no computador INF010 (10.1.0.127) em 
REM C:\Users\usuario\estagio\producao\backups_refeitorio\baixar_backups.bat

REM Qualquer alteração feita aqui também deverá ser feita no arquivo original que é utilizado pelo Agendador de Tarefas do Windows
REM NAO RODE ESSE ARQUIVO PELO VSCODE OU LINUX!!! Ele precisa ser rodado no Windows

REM Configuracao
set "DESTINO=C:\Users\usuario\estagio\producao\backups_refeitorio\backups"
set "ORIGEM=raphael@10.1.0.225:/home/raphael/backups/backups_refeitorio/backups"

echo [INFO] Iniciando download de backups...

if not exist "%DESTINO%" (
    mkdir "%DESTINO%"
)

REM Baixa apenas as categorias que devem ter copia no Windows.
for %%D in (diarios fim_mes questor) do (
    if not exist "%DESTINO%\%%D" mkdir "%DESTINO%\%%D"
    scp -r "%ORIGEM%/%%D/." "%DESTINO%\%%D"
    if errorlevel 1 (
        echo [ERRO] Falha ao baixar a categoria %%D via SCP.
        exit /b 1
    )
)

echo [INFO] Download concluido. Mantendo as 7 datas mais recentes em diarios...

REM Remove apenas diarios de producao anteriores as 7 datas mais recentes, incluindo hoje.
powershell -NoProfile -ExecutionPolicy Bypass -Command ^
    "$p='%DESTINO%\diarios'; $limite=(Get-Date).Date.AddDays(-6); if (Test-Path $p) { Get-ChildItem -Path $p -Filter 'backup_refeitorio_snd_*.sql' -File | ForEach-Object { if ($_.BaseName -match '^backup_refeitorio_snd_(\d{4}-\d{2}-\d{2})_') { $data=[datetime]::ParseExact($Matches[1], 'yyyy-MM-dd', [Globalization.CultureInfo]::InvariantCulture); if ($data -lt $limite) { Remove-Item -LiteralPath $_.FullName -Force -ErrorAction SilentlyContinue } } } }"

if errorlevel 1 (
    echo [ERRO] Falha durante limpeza dos backups antigos.
    exit /b 1
)

echo [OK] Processo finalizado com sucesso.
exit /b 0
