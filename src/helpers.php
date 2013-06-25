<?php
namespace Codegolf;

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
        file_put_contents('php://stderr', "Executing script on docker image {$image}\n");
        $containerId = trim(`docker run -d {$image} /tmp/execute /tmp/userScript`);
    } else {
        file_put_contents('php://stderr', "Executing script on docker image {$image} with constant {$constant}\n");
        $containerId = trim(`docker run -d {$image} /tmp/execute -c {$constant} /tmp/userScript`);
    }

    $exitStatus = trim(`docker wait {$containerId}`);
    $exitStatus = is_numeric($exitStatus) ? (int)$exitStatus : null;

    $output = trim(`docker logs {$containerId}`);

    return ['exitStatus' => $exitStatus, 'output' => $output];
}

function judge($language, $hole, $script)
{
    $baseDir = dirname(__DIR__);

    $hole = require_once "{$baseDir}/holes/${hole}/validate.php";

    $image = createImage($language, $script);
    if ($image === null) {
        return false;
    }

    return $hole($image);
}
