<?php
namespace Codegolf;

function localExecute($command)
{
    $pipes = null;
    $process = proc_open($command, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
    if ($process === false) {
        throw new \Exception("Error executing command '{$command}' with proc_open.");
    }

    $stdout = stream_get_contents($pipes[1]);
    $stderr = stream_get_contents($pipes[2]);
    $returnValue = proc_close($process);

    if ($returnValue !== 0) {
        throw new \Exception("Failure detected with command '{$command}' - return status {$returnValue}");
    }

    return [$stdout, $stderr];
}

function createImage($language, $script)
{
    list($tempPath) = localExecute('mktemp -d');
    $tempPath = trim($tempPath);
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
    list($containerId) = localExecute('docker run -d ' . escapeshellarg($image) . " /tmp/execute {$constant} /tmp/userScript");
    $containerId = trim($containerId);

    list($exitStatus) = localExecute('docker wait ' . escapeshellarg($containerId));
    $exitStatus = trim($exitStatus);
    $exitStatus = is_numeric($exitStatus) ? (int)$exitStatus : null;

    list($output) = localExecute('docker logs ' . escapeshellarg($containerId));

    return ['exitStatus' => $exitStatus, 'output' => $output];
}

function loadHole($holeName)
{
    $baseDir = dirname(__DIR__);

    return require_once "{$baseDir}/holes/${holeName}.php";
}

function judge($hole, $image)
{
    $constantName = getValue($hole, 'constantName');
    $constantValues = getValue($hole, 'constantValues');

    $checkResult = function($sample, $result) use($hole) {
        $result['sample'] = $sample;

        if ($result['exitStatus'] !== 0) {
            $result['result'] = false;
            return $result;
        }

        $output = $result['output'];
        if (array_key_exists('trim', $hole)) {
            $output = $hole['trim']($output);
            $sample = $hole['trim']($sample);
        }

        $result['result'] = $output === $sample;
        return $result;
    };

    if ($constantName !== null && $constantValues !== null) {
        foreach ($constantValues as $constantValue) {
            $result = $checkResult(getValue($hole, 'sample', [$constantValue]), execute($image, "{$constantName}={$constantValue}"));
            if (!$result['result']) {
                return $result;
            }
        }

        return $result;
    } else {
        return $checkResult(getValue($hole, 'sample'), execute($image));
    }
}

function getValue(array $array, $key, array $args = [])
{
    if (!array_key_exists($key, $array)) {
        return null;
    }

    if (is_callable($array[$key])) {
        return call_user_func_array($array[$key], $args);
    }

    return $array[$key];
}
