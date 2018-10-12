<?php

namespace Sonata\DevKit\Console\Command;

use Composer\Semver\Comparator;
use Composer\Semver\Semver;
use Composer\Semver\VersionParser;
use Github\Api\Repository\Releases;
use Github\Exception\ExceptionInterface;
use GitWrapper\GitWrapper;
use Packagist\Api\Result\Package;
use Sonata\DevKit\Model\Github\Release;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Filesystem\Filesystem;

/**
 * Command to controll, validate and manage releases on our repositories
 */
class ReleaseManagerCommand extends AbstractCommand
{
    /**
     * @var []
     */
    private $projects;
    /**
     * @var GitWrapper
     */
    private $gitWrapper;
    /**
     * @var Filesystem
     */
    private $fileSystem;
    /**
     * @var \Twig_Environment
     */
    private $twig;
    private $suggestedActions;

    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        parent::configure();

        $this
            ->setName('release-manager')
            ->setDescription('Validates the releaes of a bundle and marks changes to do.')
            ->addArgument('projects', InputArgument::IS_ARRAY, 'To limit the dispatcher on given project(s).', [])
            ;
    }

    protected function initialize(InputInterface $input, OutputInterface $output)
    {
        parent::initialize($input, $output);

        $this->gitWrapper = new GitWrapper();
        $this->fileSystem = new Filesystem();

        $this->projects = count($input->getArgument('projects'))
            ? $input->getArgument('projects')
            : array_keys($this->configs['projects']);
    }


    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $notConfiguredProjects = array_diff($this->projects, array_keys($this->configs['projects']));
        if (count($notConfiguredProjects)) {
            $this->io->error('Some specified projects are not configured: '.implode(', ', $notConfiguredProjects));

            return 1;
        }

        foreach ($this->projects as $name) {
            try {
                $package = $this->packagistClient->get($this->packagistGroup . '/' . $name);
                $projectConfig = $this->configs['projects'][$name];
                $this->io->title($package->getName());
                $this->validateLatestRelease($package);
                
            } catch (ExceptionInterface $e) {
                $this->io->error('Failed with message: ' . $e->getMessage());
            }
        }

        return 0;
    }

    private function validateLatestRelease(Package $package)
    {
        $this->io->section('Validations');

        $repositoryName = $this->getRepositoryName($package);
        $currentReleases = $this->getReleasesOfRepository($repositoryName);
        if (0 === count($currentReleases)) {
            $this->io->error('No releases found for '.$repositoryName);
            return;
        }

        usort($currentReleases, function (Release $a, Release $b) {
            return Comparator::greaterThan($a->getName(), $b->getName()) ? -1 : 1;
        });

        $this->io->text('All releases');
        $this->io->text(
            implode(
                ', ',
                array_map(
                    function (Release $release) {
                        return $release->getName();
                    },
                    $currentReleases
                )
            )
        );

        $this->io->text('Stable releases');
        $markedAsStable = array_filter(
            $currentReleases,
            function (Release $release) {
                return $release->isStable();
            }
        );
        $this->io->text(
            implode(
                ', ',
                array_map(
                    function (Release $release) {
                        try {
                            $stability = VersionParser::parseStability($release->getName());
                        } catch (\Exception $e) {
                            return '<error>'.$release->getName().' ('.$e->getMessage().')</error>';
                        }

                        if ($stability !== 'stable') {
                            return '<error>'.$release->getName().' ('.$stability.')</error>';
                        }

                        return '<info>'.$release->getName().' ('.$stability.')</info>';
                    },
                    $markedAsStable
                )
            )
        );
        $reallyStable = array_filter($markedAsStable, function (Release $release) {
            try {
                $stability = VersionParser::parseStability($release->getName());
            } catch (\Exception $e) {
                return false;
            }

            return ($stability === 'stable');
        });
        $latestRelease = array_shift($currentReleases);
        $this->io->text('Latest Release: '.$latestRelease->getName());

        $branches = $this->getBranchesOfRepository($repositoryName);
        $expectedByMinor = array_map(
            function (Release $release) {
                list($major, $minor) = explode('.', $release->getName());
                return $major.'.'.$minor;
            },
            $reallyStable
        );
        $expectedBranches = array_map(
            function (Release $release) {
                list($major) = explode('.', $release->getName());
                return $major.'.x';
            },
            $reallyStable
        );

        $expectedBranches = array_unique(array_merge($expectedBranches, ['master']));
        $existingBranches = array_intersect_assoc($expectedBranches, $branches);
        $diffToBranches = array_diff_assoc($expectedBranches, $existingBranches);

        $this->io->text('All Branches: '.implode(', ', $branches));
        $this->io->text('Expected Branches: '.implode(', ', $expectedBranches));
        $this->io->text('Missing branches: '.implode(', ', $diffToBranches));
        
        $this->suggestedActions[] = 'You should add following branches: '.implode(', ', $expectedBranches).' on repository.';
    }

    private function getBranchesOfRepository($repositoryName)
    {
        return array_map(function ($branch) {
            return $branch['name'];
        }, $this->githubClient->repos()->branches($this->githubGroup, $repositoryName));
    }

    /**
     * @param $repositoryname
     *
     * @return Release[]
     */
    private function getReleasesOfRepository($repositoryname)
    {
        $releases = array_filter($this->githubClient->repo()->releases()->all($this->githubGroup, $repositoryname), function ($item) use ($repositoryname) {
            $release = Release::fromArray($item);
            return preg_match('/'.$repositoryname.'/', $release->getUrl());
        });

        return array_map(
            function($item) {
                return Release::fromArray($item);
                },
            $releases
        );
    }

}