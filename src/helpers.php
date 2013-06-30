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

            $output = $result['output'];
            if (array_key_exists('trim', $hole)) {
                $output = $hole['trim']($output);
                $sample = $hole['trim']($sample);;
            }

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

        $output = $result['output'];
        if (array_key_exists('trim', $hole)) {
            $output = $hole['trim']($output);
            $sample = $hole['trim']($sample);;
        }

        return $sample === $output;
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
