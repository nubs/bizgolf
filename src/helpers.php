<?php
function createImage($language, $script)
{
    $tempPath = trim(`mktemp -d`);
    $tempBase = basename($tempPath);

    if (!copy($script, "{$tempPath}/userScript")) {
        return null;
    }

    chdir($tempPath);

    file_put_contents("{$tempPath}/Dockerfile", "FROM {$language}\nADD userScript /tmp/userScript");

    $output = null;
    $ret = null;
    exec("docker build -t {$tempBase} {$tempPath}", $output, $ret);

    return $ret === null ? null : $tempBase;
}

function execute($image, $constant = null)
{
    if ($constant === null) {
        $containerId = trim(`docker run -d {$image} /tmp/execute /tmp/userScript`);
    } else {
        $containerId = trim(`docker run -d {$image} /tmp/execute -c {$constant} /tmp/userScript`);
    }

    $exitStatus = trim(`docker wait {$containerId}`);
    $output = trim(`docker logs {$containerId}`);

    return ['exitStatus' => $exitStatus, 'output' => $output];
}
