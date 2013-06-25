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

    $hole = require_once "{$baseDir}/holes/${hole}.php";

    $image = createImage($language, $script);
    if ($image === null) {
        return false;
    }

    $constantName = getValue($hole, 'constantName');
    $constantValues = getValue($hole, 'constantValues');

    if ($constantName !== null && $constantValues !== null) {
        foreach ($constantValues as $constantValue) {
            ob_start();
            $sample = getValue($hole, 'sample', [$constantValue]);
            if ($sample === null) {
                $sample = ob_get_contents();
            }

            ob_end_clean();

            $result = execute($image, "{$constantName}={$constantValue}");
            if ($result['exitStatus'] !== 0) {
                return false;
            }

            $output = getValue($hole, 'trim', [$result['output']]);
            $sample = getValue($hole, 'trim', [$sample]);

            if ($output !== $sample) {
                return false;
            }
        }

        return true;
    } else {
        ob_start();
        $sample = getValue($hole, 'sample');
        if ($sample === null) {
            $sample = ob_get_contents();
        }

        ob_end_clean();

        $result = execute($image);
        if ($result['exitStatus'] !== 0) {
            return false;
        }

        $output = getValue($hole, 'trim', [$result['output']]);
        $sample = getValue($hole, 'trim', [$sample]);

        return $sample === $output;
    }
}

function getValue(array $array, $value, array $args = [])
{
    if (empty($array[$value])) {
        return null;
    }

    if (is_callable($array[$value])) {
        return call_user_func_array($array[$value], $args);
    }

    return $array[$value];
}
