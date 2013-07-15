# BizGolf
A library that interacts with [Docker](http://www.docker.io) in order to execute user's [code golf](http://en.wikipedia.org/wiki/Code_golf) submissions and verify their correctness.

## What is Code Golf?
Code golf is a programming competition where the aim is to solve problems using the fewest bytes of code.  This tests programmers logic skills and the knowledge of their programming languages in all the corner cases of syntax.

## What is BizGolf?
Bizgolf is an attempt to create an open source codegolf library and hosting platform for hosting standalone code golf events.

Ideas for events:
* Annual code golf tournament with one active hole at a time.
* Have a competition between 2 or more development teams.
* During a hackathon, their could be an ongoing codegolf tournament on the side.

## Languages Supported
Right now, just PHP is available, but adding other languages shouldn't be too difficult.  The tricky bit is just in locking the language features down like shell execution, web access, etc., that would allow for "cheating".

* PHP - versions 5.4.16, 5.5.0

## Included Holes
A growing list of holes will be included along with this library, making it easy to try out and run an impromptu event.  Adding your own holes is easy too, though.

* 99 Bottles of Beer on the Wall
* Fizzbuzz
* Hello World

## Requirements
The host library is currently written in PHP, but it's a small library that could be easily ported to another language.  Some features from php 5.4 are used, so make sure you are running at least 5.4.

Required libraries are pulled in using [Composer](http://getcomposer.org).  They will be automatically installed if you use composer to install the library and use its autoloader.

For executing user code, [Docker](http://www.docker.io) is used.  It has somewhat strict requirements, but people have gotten it working on a wide number of environments and there is ongoing efforts to make it operable on many more environments.  Take a look at its documentation for getting it installed on your system.

No additional requirements should be needed, docker takes care of getting the execution environment setup for each language.

## Library Usage
The expected usage of this library is through the included php functions.  Add this library to your php project via composer:
```json
{
    "require": {
        "nubs/bizgolf": "dev-master"
    }
}
```

Composer's autoloader will automatically include the functions for use in your project.  The functions' APIs are below.

```php
/**
 * Creates a docker image based on the requested language with the given user
 * script added to the image for execution.
 *
 * @param string $language One of the supported languages.
 * @param string $script The file path to the user's submission to test.
 * @return string The docker image id that was created.
 * @throws Exception if unable to create docker image
 */
/**
 * Creates a docker image based on the requested language with the given user
 * script added to the image for execution.
 *
 * @param string $languageName One of the supported languages.
 * @param string $script The file path to the user's submission to test.
 * @param string|null $constantName The name of the constant to set, if a
 *     constant is being used.
 * @param mixed|null $constantValue The value of the constant to set, if a
 *     constant is being used.
 * @return array A description of the docker image that was created.
 * @throws Exception if unable to create docker image
 */
function createImage($language, $script);

/**
 * Loads the hole configuration for an included hole.  If you want to add your
 * own holes outside of this project, you don't need to call this function.
 *
 * @param string $holeName One of the included holes.
 * @return array The hole's configuration.  Included fields:
 *     string constantName (optional) The name of the constant that will hold
 *         input.
 *         This may be a callable as well, with 0 arguments.
 *     array constantValues (optional) The different values of input to test.
 *         This may be a callable as well, with 0 arguments.
 *     callable trim (optional) What kind of trim to apply to the results
 *         before comparison.
 *     string sample The expected output for the hole.
 *         This may be a callable as well, with 1 argument containing the
           constant value for input.
 */
function loadHole($holeName);

/**
 * Judges the user submission on the given image against the given hole
 * configuration.
 *
 * @param array $hole The hole's configuration.  @see loadHole() for details.
 * @param string $languageName One of the supported languages.
 * @param string $script The file path to the user's submission to test.
 * @return array The results of judging the submission and the details of the
 *     submission's last run.  Included fields:
 *     bool result Whether the submission passed the tests or not.
 *     int exitStatus The exit status of the command.
 *     string output The output, trimmed according to the rules of the hole.
 *     string sample The expected output, trimmed according to the rules of the
 *         hole.
 *     string stderr The stderr output.
 *     string|null constantName The constant's name, if used.
 *     mixed|null constantValue The constant's value, if used.
 */
function judge($hole, $image);
```

Here's an example of how it could be used to judge a user's submission:
```php
<?php
$holeName = 'fizzbuzz';
$language = 'php-5.5';
$userScript = $_FILES['submission']['tmp_name'];
$result = \Bizgolf\judge(\Bizgolf\loadHole($holeName), $language, $userScript);
if ($result['result']) {
    echo "Successful submission!\n";
} else {
    echo "Submission failed validation.  Try again.\n";
}
```

## CLI Usage
There is a limited php command line `judge` command included in the bin directory.  Its usage is:
```bash
judge LANGUAGE HOLE USER_SUBMISSION
```

The languages and holes are the file names inside this repository and the user submission is the path to the script to judge.  This command will exit with a status of 0 if the submission passed the hole, and a status of 1 if it did not.

## Contributing
Any changes, suggestions, or bug reports are welcome to be submitted on github.  Pull requests are welcome!

### New Languages
Please try to make sure that the language locks down behavior so that no network access, filesystem access, or process execution is allowed.  This is to remove common "cheating" avenues such as downloading the result from a website hosting the solutions.

A language should include a language definition which is a php script that returns an array containing:
* a `tagName` (which also doubles as the directory name where the Dockerfile is located),
* an `addConstant` function, which takes the user's script, a constant name, and a constant value as parameters and should return the script but with code necessary to set a constant with the name given to the value given,
* and an `executeCommand` string which gives the command to run.  This script will be passed the path to the user's script and should exit with the status returned from executing the user script and forward along stdout and stderr from that script.

There should be a Dockerfile located in languages/`tagName`/Dockerfile that should create a new Docker image with the target language installed and configured to lock down access as described.

### New Holes
New holes are a simple php file that returns an array containing the details of what the hole expects.  The below example (taken from fizzbuzz) shows all of the fields that are allowed:
```php
<?php
return [
    'constantName' => 'NUM',
    'constantValues' => function() {
        $values = [100, 1000];
        for ($i = 0; $i < 8; $i++) {
            $values[] = rand(101, 999);
        }

        return $values;
    },
    'trim' => 'rtrim',
    'sample' => function($num) {
        $result = '';
        for ($i = 1; $i <= $num; $i++) {
            if ($i % 15 == 0) {
                $result .= "FizzBuzz\n";
            } elseif ($i % 3 == 0) {
                $result .= "Fizz\n";
            } elseif ($i % 5 == 0) {
                $result .= "Buzz\n";
            } else {
                $result .= "$i\n";
            }
        }

        return $result;
    },
];
```

Try to make sure that your hole will provide an appropriate challenge and could work well in many languages.

## License
This project is licensed under the MIT License.
