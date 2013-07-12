<?php
namespace Bizgolf;

function localExecute($command, $timeout = null)
{
    $pipes = null;
    $process = proc_open($command, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
    if ($process === false) {
        throw new \Exception("Error executing command '{$command}' with proc_open.");
    }

    if ($timeout !== null) {
        $timeout *= 1000000;
    }

    stream_set_blocking($pipes[1], 0);
    stream_set_blocking($pipes[2], 0);
    $stdout = '';
    $stderr = '';
    $exitCode = null;
    while ($timeout === null || $timeout > 0) {
        $start = microtime(true);

        $read = [$pipes[1], $pipes[2]];
        $other = [];
        stream_select($read, $other, $other, 0, $timeout);

        $status = proc_get_status($process);

        $stdout .= stream_get_contents($pipes[1]);
        $stderr .= stream_get_contents($pipes[2]);

        if (!$status['running']) {
            $exitCode = $status['exitcode'];
            break;
        }

        if ($timeout !== null) {
            $timeout -= (microtime(true) - $start) * 1000000;
        }
    }

    proc_terminate($process, 9);
    $closeStatus = proc_close($process);
    if ($exitCode === null) {
        $exitCode = $closeStatus;
    }

    if ($exitCode !== 0) {
        throw new \Exception("Failure detected with command '{$command}' - return status {$exitCode}");
    }

    return [$stdout, $stderr];
}

function verifyImageForLanguage($language)
{
    list($imageId) = localExecute('docker images -q ' . escapeshellarg($language));
    if ($imageId === '') {
        file_put_contents('php://stderr', "Building image for language {$language}.\n");

        $baseDir = dirname(__DIR__);
        localExecute('docker build -t ' . escapeshellarg($language) . ' ' . escapeshellarg("{$baseDir}/languages/{$language}"));
    }
}

/**
 * Creates a docker image based on the requested language with the given user script added to the image for execution.
 *
 * @param string $language One of the supported languages.
 * @param string $script The file path to the user's submission to test.
 * @return string The docker image id that was created.
 * @throws Exception if unable to create docker image
 */
function createImage($language, $script)
{
    verifyImageForLanguage($language);

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
    $constantArgument = '';
    if ($constant !== null) {
        $progressString .= " with constant {$constant}";
        $constantArgument = '-c ' . escapeshellarg($constant);
    }

    file_put_contents('php://stderr', "{$progressString}\n");
    list($containerId) = localExecute('docker run -d ' . escapeshellarg($image) . " /tmp/execute {$constantArgument} /tmp/userScript");
    $containerId = trim($containerId);

    list($exitStatus) = localExecute('docker wait ' . escapeshellarg($containerId), 10);
    $exitStatus = trim($exitStatus);
    $exitStatus = is_numeric($exitStatus) ? (int)$exitStatus : null;

    list($output, $stderr) = localExecute('docker logs ' . escapeshellarg($containerId));

    return ['exitStatus' => $exitStatus, 'output' => $output, 'stderr' => $stderr, 'constant' => $constant];
}

/**
 * Loads the hole configuration for an included hole.  If you want to add your own holes outside of this project, you don't need to call this
 * function.
 *
 * @param string $holeName One of the included holes.
 * @return array The hole's configuration.  Included fields:
 *     string constantName (optional) The name of the constant that will hold input.
 *         This may be a callable as well, with 0 arguments.
 *     array constantValues (optional) The different values of input to test.
 *         This may be a callable as well, with 0 arguments.
 *     callable trim (optional) What kind of trim to apply to the results before comparison.
 *     string sample The expected output for the hole.
 *         This may be a callable as well, with 1 argument containing the constant value for input.
 */
function loadHole($holeName)
{
    $baseDir = dirname(__DIR__);

    return require "{$baseDir}/holes/${holeName}.php";
}

/**
 * Judges the user submission on the given image against the given hole configuration.
 *
 * @param array $hole The hole's configuration.  @see loadHole() for details.
 * @param string $image The image with the user's submission for a single language.  @see createImage() for details.
 * @return array The results of judging the submission and the details of the submission's last run.  Included fields:
 *     bool result Whether the submission passed the tests or not.
 *     int exitStatus The exit status of the command.
 *     string output The output, trimmed according to the rules of the hole.
 *     string sample The expected output, trimmed according to the rules of the hole.
 *     string stderr The stderr output.
 *     string constant The constant variable and its value.
 */
function judge($hole, $image)
{
    $constantName = empty($hole['constantName']) ? null : $hole['constantName'];
    $constantValues = empty($hole['constantValues']) ? null : $hole['constantValues'];
    if (is_callable($constantValues)) {
        $constantValues = call_user_func($constantValues);
    }

    $checkResult = function($sample, $result) use($hole) {
        $result['sample'] = $sample;

        if ($result['exitStatus'] !== 0) {
            $result['result'] = false;
            return $result;
        }

        if (array_key_exists('trim', $hole)) {
            $result['output'] = $hole['trim']($result['output']);
            $result['sample'] = $hole['trim']($result['sample']);
        }

        $result['result'] = $result['output'] === $result['sample'];
        return $result;
    };

    if ($constantName !== null && $constantValues !== null) {
        foreach ($constantValues as $constantValue) {
            $result = $checkResult(call_user_func($hole['sample'], $constantValue), execute($image, "{$constantName}={$constantValue}"));
            if (!$result['result']) {
                return $result;
            }
        }

        return $result;
    } else {
        $sample = $hole['sample'];
        if (is_callable($sample)) {
            $sample = call_user_func($sample);
        }

        return $checkResult($sample, execute($image));
    }
}
