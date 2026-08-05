@echo off
setlocal EnableExtensions

REM Este arquivo é uma cópia do original que fica no computador INF010 (10.1.0.127) em 
REM C:\Users\usuario\estagio\producao\backups_refeitorio\baixar_backups.bat

REM Qualquer alteração feita aqui também deverá ser feita no arquivo original que é utilizado pelo Agendador de Tarefas do Windows
REM NAO RODE ESSE ARQUIVO PELO VSCODE OU LINUX!!! Ele precisa ser rodado no Windows

REM Configuracao
set "DESTINO=C:\Users\usuario\estagio\producao\backups_refeitorio\backups"
set "ORIGEM=usuario@10.1.0.225:/home/usuario/raphael/projetos/backups_refeitorio/backups/."

echo [INFO] Iniciando download de backups...

if not exist "%DESTINO%" (
    mkdir "%DESTINO%"
)

scp -r %ORIGEM% "%DESTINO%"
if errorlevel 1 (
    echo [ERRO] Falha ao baixar backups via SCP.
    exit /b 1
)

echo [INFO] Download concluido. Mantendo apenas especiais, hoje e ontem...

REM Datas para manter
for /f %%a in ('powershell -NoProfile -Command "Get-Date -Format yyyy-MM-dd"') do set "HOJE=%%a"
for /f %%a in ('powershell -NoProfile -Command "(Get-Date).AddDays(-1).ToString(\"yyyy-MM-dd\")"') do set "ONTEM=%%a"

REM Remove arquivos que NAO sejam especiais, hoje ou ontem
powershell -NoProfile -ExecutionPolicy Bypass -Command ^
    "$p='%DESTINO%'; $hoje='%HOJE%'; $ontem='%ONTEM%'; if (Test-Path $p) { Get-ChildItem -Path $p -Filter 'backup_refeitorio_snd_*.sql' -File | ForEach-Object { $n=$_.Name; if ($n -notmatch '_FIM_' -and $n -notmatch $hoje -and $n -notmatch $ontem) { Remove-Item -LiteralPath $_.FullName -Force -ErrorAction SilentlyContinue } } }"

if errorlevel 1 (
    echo [ERRO] Falha durante limpeza dos backups antigos.
    exit /b 1
)

echo [OK] Processo finalizado com sucesso.
exit /b 0