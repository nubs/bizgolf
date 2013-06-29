<?php
namespace Codegolf;

function localExecute($command)
{
    $output = null;
    $returnValue = null;
    exec($command, $output, $returnValue);

    if ($returnValue !== 0) {
        throw new \Exception("Failure detected with command {$command} - return status {$returnValue}");
    }

    return implode("\n", $output);
}

function createImage($language, $script)
{
    $tempPath = localExecute('mktemp -d');
    $tempBase = basename($tempPath);

    if (!copy($script, "{$tempPath}/userScript")) {
        throw new \Exception("Failed to copy script to {$tempPath}");
    }

    if (!file_put_contents("{$tempPath}/Dockerfile", "FROM {$language}\nADD userScript /tmp/userScript")) {
        throw new \Exception('Failed to create Dockerfile');
    }

    localExecute('docker build -t ' . escapeshellarg($tempBase) . ' ' . escapeshellarg($tempPath));

    return $tempBase;
}

function execute($image, $constant = null)
{
    $progressString = "Executing script on docker image {$image}";
    if ($constant !== null) {
        $progressString .= " with constant {$constant}";
        $constant = '-c ' . escapeshellarg($constant);
    }

    file_put_contents('php://stderr', "{$progressString}\n");
    $containerId = localExecute('docker run -d ' . escapeshellarg($image) . " /tmp/execute {$constant} /tmp/userScript");

    $exitStatus = localExecute('docker wait ' . escapeshellarg($containerId));
    $exitStatus = is_numeric($exitStatus) ? (int)$exitStatus : null;

    $output = localExecute('docker logs ' . escapeshellarg($containerId));

    return ['exitStatus' => $exitStatus, 'output' => $output];
}

function judge($language, $hole, $script)
{
    $baseDir = dirname(__DIR__);

    $hole = require_once "{$baseDir}/holes/${hole}.php";

    $image = createImage($language, $script);

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
