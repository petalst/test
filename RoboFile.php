<?php
/**
 * This is project's console commands configuration for Robo task runner.
 *
 * @see http://robo.li/
 */
class RoboFile extends \Robo\Tasks
{

    /**
     * @var string $host            Remote host.
     */
    var $host = "maco.maksimer.net";
    /**
     * @var string $ssh_user        User on remote server, used for SSH login.
     */
    var $ssh_user = "maco";

    /**
     * @var string $github_uri Github uri
     */
    var $github_uri = 'maksimer/maco';



    public function pull() {
        $this->taskGitStack()
            ->stopOnFail()
            ->pull()
            ->run();
    }


    public function push($branch, $version) {

        $prerelease = false;
        if ('yes' === $this->ask('Is this a pre-release? (yes/no)')) {
            $prerelease = true;
        }
        // comment format: new feature/bugfix: System/maco Change
        $this->taskGitStack()
            ->stopOnFail()
            ->add('-A')
            ->commit('adding everything')
            ->push('origin',$branch)
            ->tag($version)

            ->push('origin',$version)
            ->run();
    }


    public function release($version, $description) {

        $this->yell("Releasing maco $version");

        $this->pull();

//        // Stop if test fails
//        if ($this->test() !== true) {
//            $this->writeln('Tests failed!');
//            $this->say('Script stopped.');
//            exit;
//        }

        $prerelease = false;
        if ('yes' === $this->ask('Is this a pre-release? (yes/no)')) {
            $prerelease = true;
        }

        $this->version($version);
        //$this->push();
        $this->taskGitHubRelease($version)
            ->uri($this->github_uri)
            ->tag($version)
            ->prerelease($prerelease)
            ->description($description)
            ->run();

    }

    /**
     * @param $account
     */
    public function deploy($account) {
        $this->yell('Deploying to '.$account);

        $this->pull();


        $version = $this->getCurrentVersion();

        $deploy = $this->taskSshExec($this->host)
            ->stopOnFail()
            ->user($this->ssh_user)
            ->remoteDir('/var/www/'.$account)
            ->exec('cp -a /maco-master-from-git/* ./')
            ->exec('chmod 0750 var/log')
            ->exec('composer update');

        if ('yes' === $this->ask('Do you want to deploy to '.$account.'? (yes/no)')) {
            $this->say('Deploying to '. $account);
            $deploy->run();
            $this->say('Deployed new code to '. $account);
        }


        $this->updateVersionInAdmin($account, $version);

    }


    public function version($version) {
        // Write the result to a file.
        $this->say('Updating maco version to '. $version);
        return $this->taskReplaceInFile(__DIR__.'/.version')
            ->regex("#Version = [^']*#")
            ->to('Version = '.$version)
            ->run();

    }


    // define public methods as commands
    public function test()
    {
        // runs PHPUnit tests
        return $this->taskPHPUnit('../../vendor/phpunit/phpunit/phpunit')
            ->bootstrap('../bootstrap.php')
            ->dir(realpath(dirname(__FILE__)) . '/tests/application/')
            ->file('library/')
            ->run()
            ->wasSuccessful();
    }

    private function getCurrentVersion() {
        $string = file_get_contents('.version');
        $version = str_replace('Version = ','', $string);
        return $version;
    }

    /**
     * @param $account
     * @param $version
     */
    private function updateVersionInAdmin($account, $version)
    {

        $user = exec('git config --get user.name');

        $token = crypt($version, '41601');
        $payload = json_encode([
            'version' => $version,
            'installation' => $account,
            'user' => $user
        ]);
        $url = 'https://admin.maco.io/api/v1/version';
        // Send version to maco admin register
        $cmd = "curl -X  PUT  ";
        $cmd .= "-H 'Content-Type: application/json' ";
        $cmd .= "-H 'Authorization:  $token' ";
        $cmd .= " -d '" . $payload . "' " . "'" . $url . "'";
        $cmd .= " > /dev/null 2>&1 &";

        exec($cmd, $output, $exit);

    }
}