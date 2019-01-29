<?php

/*
 * This file is part of the Sonata Project package.
 *
 * (c) Thomas Rabaix <thomas.rabaix@sonata-project.org>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Sonata\DevKit\Console\Command;

use Maknz\Slack\Client as SlackClient;
use Packagist\Api\Result\Package;
use Sonata\DevKit\Config\DevKitConfiguration;
use Sonata\DevKit\Config\ProjectsConfiguration;
use Sonata\DevKit\GithubClient;
use Symfony\Component\Config\Definition\Processor;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Yaml\Yaml;
use Composer\Semver;
use Packagist\Api\Result\Package\Version;

/**
 * @author Sullivan Senechal <soullivaneuh@gmail.com>
 */
abstract class AbstractCommand extends Command
{
    protected $githubGroup;
    protected $githubUser;
    protected $githubEmail;
    protected $packagistGroup;
    protected $homepage;

    /**
     * @var SymfonyStyle
     */
    protected $io;

    /**
     * @var array
     */
    protected $configs;

    /**
     * @var string|null
     */
    protected $githubAuthKey = null;

    /**
     * @var \Packagist\Api\Client
     */
    protected $packagistClient;

    /**
     * @var GithubClient
     */
    protected $githubClient = false;

    /**
     * @var \Github\ResultPager
     */
    protected $githubPaginator;

    /**
     * @var SlackClient
     */
    protected $slackClient;

    /**
     * {@inheritdoc}
     */
    protected function initialize(InputInterface $input, OutputInterface $output)
    {
        $this->io = new SymfonyStyle($input, $output);

        $processor = new Processor();
        $devKitConfigs = $processor->processConfiguration(new DevKitConfiguration(), [
            'cmf' => Yaml::parse(file_get_contents(__DIR__.'/../../../config/dev-kit.yml')),
        ]);
        $projectsConfigs = $processor->processConfiguration(new ProjectsConfiguration($devKitConfigs), [
            'cmf' => ['projects' => Yaml::parse(file_get_contents(__DIR__.'/../../../config/projects.yml'))],
        ]);
        $this->configs = array_merge($devKitConfigs, $projectsConfigs);

        $this->githubAuthKey = getenv('GITHUB_OAUTH_TOKEN');
        $this->githubGroup = getenv('GITHUB_GROUP');
        $this->githubUser = getenv('GITHUB_USER');
        $this->githubEmail = getenv('GITHUB_EMAIL');
        $this->packagistGroup = getenv('PACKAGIST_GROUP');
        $this->homepage = getenv('HOMEPAGE');

        $this->packagistClient = new \Packagist\Api\Client();

        $this->githubClient = new GithubClient();
        $this->githubPaginator = new \Github\ResultPager($this->githubClient);
        if ($this->githubAuthKey) {
            $this->githubClient->authenticate($this->githubAuthKey, null, \Github\Client::AUTH_HTTP_TOKEN);
        }

        $this->slackClient = new SlackClient(getenv('SLACK_HOOK'));
    }

    /**
     * Returns repository name without vendor prefix.
     *
     * @param Package $package
     *
     * @return string
     */
    final protected function getRepositoryName(Package $package)
    {
        $repositoryArray = explode('/', $package->getRepository());

        return str_replace('.git', '', end($repositoryArray));
    }

    /**
     * @param Packaage $package
     * 
     * @return []
     */
    final protected function getStableVersions(Package $package): array
    {
        $stableVersions = array_filter(
            array_keys($package->getVersions()),
            function ($version) {
                try {
                    if ('stable' !== Semver\VersionParser::parseStability($version)) {
                        return false;
                    }
                } catch (\Exception $e) {
                    return false;
                }

                return true;
            }
        );

        return Semver\Semver::rsort($stableVersions);
    }
}
