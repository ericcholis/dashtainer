<?php

namespace Dashtainer\Domain\Docker\ServiceWorker;

use Dashtainer\Entity;
use Dashtainer\Form;

class PostgreSQL extends WorkerAbstract implements WorkerInterface
{
    public function getServiceType() : Entity\Docker\ServiceType
    {
        if (!$this->serviceType) {
            $this->serviceType = $this->serviceTypeRepo->findBySlug('postgresql');
        }

        return $this->serviceType;
    }

    public function getCreateForm() : Form\Docker\Service\CreateAbstract
    {
        return new Form\Docker\Service\PostgreSQLCreate();
    }

    /**
     * @param Form\Docker\Service\PostgreSQLCreate $form
     * @return Entity\Docker\Service
     */
    public function create($form) : Entity\Docker\Service
    {
        $service = new Entity\Docker\Service();
        $service->setName($form->name)
            ->setType($form->type)
            ->setProject($form->project);

        $version = (string) number_format($form->version, 1);

        $service->setImage("postgres:{$version}")
            ->setRestart(Entity\Docker\Service::RESTART_ALWAYS);

        $service->setEnvironments([
            'POSTGRES_DB_FILE'       => '/run/secrets/postgres_db',
            'POSTGRES_USER_FILE'     => '/run/secrets/postgres_user',
            'POSTGRES_PASSWORD_FILE' => '/run/secrets/postgres_password',
        ]);

        $this->serviceRepo->save($service);

        $form->secrets['postgres_host']['data'] = $service->getSlug();

        $this->addToPrivateNetworks($service, $form);
        $this->createSecrets($service, $form);
        $this->createVolumes($service, $form);

        $versionMeta = new Entity\Docker\ServiceMeta();
        $versionMeta->setName('version')
            ->setData([$form->version])
            ->setService($service);

        $service->addMeta($versionMeta);

        $portMetaData = $form->port_confirm ? [$form->port] : [];
        $servicePort  = $form->port_confirm ? ["{$form->port}:5432"] : [];

        $portMeta = new Entity\Docker\ServiceMeta();
        $portMeta->setName('bind-port')
            ->setData($portMetaData)
            ->setService($service);

        $service->addMeta($portMeta)
            ->setPorts($servicePort);

        $this->serviceRepo->save($versionMeta, $portMeta, $service);

        return $service;
    }

    public function getCreateParams(Entity\Docker\Project $project) : array
    {
        return array_merge(parent::getCreateParams($project), [
            'bindPort'      => $this->getOpenBindPort($project),
            'fileHighlight' => 'ini',
        ]);
    }

    public function getViewParams(Entity\Docker\Service $service) : array
    {
        $version   = $service->getMeta('version')->getData()[0];
        $version   = (string) number_format($version, 1);

        $bindPortMeta = $service->getMeta('bind-port');
        $bindPort     = $bindPortMeta->getData()[0]
            ?? $this->getOpenBindPort($service->getProject());
        $portConfirm  = $bindPortMeta->getData()[0] ?? false;

        return array_merge(parent::getViewParams($service), [
            'version'       => $version,
            'bindPort'      => $bindPort,
            'portConfirm'   => $portConfirm,
            'fileHighlight' => 'ini',
        ]);
    }

    /**
     * @param Entity\Docker\Service                $service
     * @param Form\Docker\Service\PostgreSQLCreate $form
     * @return Entity\Docker\Service
     */
    public function update(
        Entity\Docker\Service $service,
        $form
    ) : Entity\Docker\Service {
        $portMetaData = $form->port_confirm ? [$form->port] : [];
        $servicePort  = $form->port_confirm ? ["{$form->port}:5432"] : [];

        $portMeta = $service->getMeta('bind-port');
        $portMeta->setData($portMetaData);

        $this->serviceRepo->save($portMeta);

        $service->setPorts($servicePort);

        $this->addToPrivateNetworks($service, $form);
        $this->updateSecrets($service, $form);
        $this->updateVolumes($service, $form);

        $this->serviceRepo->save($service);

        return $service;
    }

    protected function getOpenBindPort(Entity\Docker\Project $project) : int
    {
        $bindPortMetas = $this->serviceRepo->getProjectBindPorts($project);

        $ports = [];
        foreach ($bindPortMetas as $meta) {
            if (!$data = $meta->getData()) {
                continue;
            }

            $ports []= $data[0];
        }

        for ($i = 5433; $i < 65535; $i++) {
            if (!in_array($i, $ports)) {
                return $i;
            }
        }

        return 5432;
    }

    protected function internalVolumesArray() : array
    {
        return [
            'files' => [
                "conf-{$this->version}",
            ],
            'other' => [
                'datadir',
            ],
        ];
    }

    protected function internalSecretsArray() : array
    {
        return [
            'postgres_host',
            'postgres_db',
            'postgres_user',
            'postgres_password',
        ];
    }
}
