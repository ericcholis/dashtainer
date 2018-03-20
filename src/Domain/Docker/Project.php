<?php

namespace Dashtainer\Domain\Docker;

use Dashtainer\Entity;
use Dashtainer\Form;
use Dashtainer\Repository;
use Dashtainer\Util;

class Project
{
    /** @var Repository\Docker\Project */
    protected $repo;

    public function __construct(Repository\Docker\Project $repo)
    {
        $this->repo = $repo;
    }

    public function createProjectFromForm(
        Form\Docker\ProjectCreateUpdate $form,
        Entity\User $user
    ) : Entity\Docker\Project {
        $project = new Entity\Docker\Project();
        $project->fromArray($form->toArray());
        $project->setUser($user);

        $this->repo->save($project);

        $hostname = Util\Strings::hostname($project->getSlug());

        $publicNetwork = new Entity\Docker\Network();
        $publicNetwork->setName("{$hostname}-public")
            ->setIsRemovable(false)
            ->setIsPrimaryPublic(true)
            ->setExternal('traefik_webgateway')
            ->setProject($project);

        $privateNetwork = new Entity\Docker\Network();
        $privateNetwork->setName("{$hostname}-private")
            ->setIsRemovable(false)
            ->setIsPrimaryPrivate(true)
            ->setProject($project);

        $project->addNetwork($publicNetwork)
            ->addNetwork($privateNetwork);

        $this->repo->save($publicNetwork, $privateNetwork, $project);

        return $project;
    }

    public function delete(Entity\Docker\Project $project)
    {
        $deleted = [];
        $saved   = [];

        foreach ($project->getServices() as $service) {
            foreach ($service->getMetas() as $meta) {
                $service->removeMeta($meta);

                $deleted []= $meta;
            }

            foreach ($service->getNetworks() as $network) {
                $service->removeNetwork($network);
                $network->removeService($service);

                $saved []= $network;
            }

            foreach ($service->getSecrets() as $secret) {
                $service->removeSecret($secret);
                $secret->removeService($service);

                $saved []= $secret;
            }

            foreach ($service->getVolumes() as $volume) {
                $service->removeVolume($volume);

                if ($projectVolume = $volume->getProjectVolume()) {
                    $volume->setProjectVolume(null);
                    $projectVolume->removeServiceVolume($volume);
                }

                $saved   []= $volume;
                $deleted []= $volume;
            }

            $project->removeService($service);

            $saved   []= $service;
            $deleted []= $service;
        }

        foreach ($project->getNetworks() as $network) {
            $project->removeNetwork($network);

            $saved   []= $network;
            $deleted []= $network;
        }

        foreach ($project->getSecrets() as $secret) {
            $project->removeSecret($secret);

            $deleted []= $secret;
        }

        foreach ($project->getVolumes() as $volume) {
            $project->removeVolume($volume);

            $deleted []= $volume;
        }

        $deleted []= $project;

        $this->repo->save(...$saved);
        $this->repo->delete(...$deleted);
    }
}