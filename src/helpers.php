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

function loadLanguage($languageName)
{
    $baseDir = dirname(__DIR__);
    $language = require "{$baseDir}/languages/${languageName}.php";

    list($imageId) = localExecute('docker images -q ' . escapeshellarg($language['tagName']));
    if ($imageId === '') {
        file_put_contents('php://stderr', "Building image for language {$language['tagName']}.\n");

        $baseDir = dirname(__DIR__);
        localExecute(
            'docker build -t ' . escapeshellarg($language['tagName']) . ' ' .  escapeshellarg("{$baseDir}/languages/{$language['tagName']}")
        );
    }

    return $language;
}

/**
 * Creates a docker image based on the requested language with the given user script added to the image for execution.
 *
 * @param string $languageName One of the supported languages.
 * @param string $script The file path to the user's submission to test.
 * @param string|null $constantName The name of the constant to set, if a constant is being used.
 * @param mixed|null $constantValue The value of the constant to set, if a constant is being used.
 * @return array A description of the docker image that was created.
 * @throws Exception if unable to create docker image
 */
function createImage($languageName, $script, $constantName = null, $constantValue = null)
{
    $image = loadLanguage($languageName);

    $scriptContents = file_get_contents($script);
    if ($scriptContents === false) {
        throw new \Exception('Failed to read user script.');
    }

    if ($constantName !== null) {
        $scriptContents = $image['addConstant']($scriptContents, $constantName, $constantValue);
    }

    return buildImage($image, ['/tmp/userScript' => $scriptContents]);
}

function buildImage($baseImage, array $files, array $commands = [])
{
    list($tempPath) = localExecute('mktemp -d');
    $tempPath = trim($tempPath);
    $tempBase = basename($tempPath);

    $dockerCommands = ["FROM {$baseImage['tagName']}"];

    foreach ($files as $fileName => $fileContents) {
        $tempFile = tempnam($tempPath, 'bg');
        if (!file_put_contents($tempFile, $fileContents)) {
            throw new \Exception('Failed to create file in temp directory.');
        }

        $dockerCommands[] = 'ADD ' . basename($tempFile) . " {$fileName}";
    }

    $dockerCommands = array_merge($dockerCommands, $commands);
    if (!file_put_contents("{$tempPath}/Dockerfile", implode("\n", $dockerCommands))) {
        throw new \Exception('Failed to create Dockerfile');
    }

    localExecute('docker build -t ' . escapeshellarg($tempBase) . ' ' . escapeshellarg($tempPath));
    $baseImage['tagName'] = $tempBase;

    return $baseImage;
}

function execute(array $image)
{
    $tagName = $image['tagName'];
    $executeCommand = $image['executeCommand'];
    file_put_contents('php://stderr',  "Executing script on docker image {$tagName}\n");
    list($containerId) = localExecute('docker run -d ' . escapeshellarg($tagName) . ' ' . escapeshellarg($executeCommand) . ' /tmp/userScript');
    $containerId = trim($containerId);

    list($exitStatus) = localExecute('docker wait ' . escapeshellarg($containerId), 10);
    $exitStatus = trim($exitStatus);
    $exitStatus = is_numeric($exitStatus) ? (int)$exitStatus : null;

    list($output, $stderr) = localExecute('docker logs ' . escapeshellarg($containerId));

    return ['exitStatus' => $exitStatus, 'output' => $output, 'stderr' => $stderr];
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
 * Judges the user submission for a language against the given hole configuration.
 *
 * @param array $hole The hole's configuration.  @see loadHole() for details.
 * @param string $languageName One of the supported languages.
 * @param string $script The file path to the user's submission to test.
 * @return array The results of judging the submission and the details of the submission's last run.  Included fields:
 *     bool result Whether the submission passed the tests or not.
 *     int exitStatus The exit status of the command.
 *     string output The output, trimmed according to the rules of the hole.
 *     string sample The expected output, trimmed according to the rules of the hole.
 *     string stderr The stderr output.
 *     string|null constantName The constant's name, if used.
 *     mixed|null constantValue The constant's value, if used.
 */
function judge($hole, $languageName, $script)
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
            $image = createImage($languageName, $script, $constantName, $constantValue);
            $result = $checkResult(call_user_func($hole['sample'], $constantValue), execute($image));
            $result['constantName'] = $constantName;
            $result['constantValue'] = $constantValue;

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

        $image = createImage($languageName, $script);
        $result = $checkResult($sample, execute($image));
        $result['constantName'] = null;
        $result['constantValue'] = null;
    }
}
