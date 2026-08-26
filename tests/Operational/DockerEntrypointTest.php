<?php

namespace Tests\Operational;

use Tests\Support\ProcessTestCase;

class DockerEntrypointTest extends ProcessTestCase
{
    public function testEntrypointAjustaPermissoesSemReinstalarDependencias(): void
    {
        $script = <<<'SH'
mkdir -p /var/www/html/vendor /var/www/html/storage/logs /backups/questor
touch /var/www/html/vendor/autoload.php
touch /var/www/html/storage/logs/app.log
touch /backups/questor/questor.sql
chmod 0777 /var/www/html/storage/logs/app.log /backups/questor/questor.sql
/usr/local/bin/entrypoint.sh /bin/true
stat -c '%a %U:%G' /var/www/html/storage/logs
stat -c '%a %U:%G' /var/www/html/storage/logs/app.log
stat -c '%a %U:%G' /backups/questor
stat -c '%a' /backups/questor/questor.sql
SH;

        $result = $this->runProcess([
            'docker', 'run', '--rm', '--network', 'none', '--read-only',
            '--tmpfs', '/var/www/html', '--tmpfs', '/backups',
            '--env', 'BACKUP_DIR=/backups',
            '--entrypoint', '/bin/sh',
            'refeitorio-entrypoint-test',
            '-c', $script,
        ]);

        $this->assertSame(0, $result['exitCode'], $result['stderr']);
        $this->assertSame([
            '2775 www-data:www-data',
            '664 www-data:www-data',
            '2775 www-data:www-data',
            '664',
        ], preg_split('/\R/', trim($result['stdout'])));
    }
}
