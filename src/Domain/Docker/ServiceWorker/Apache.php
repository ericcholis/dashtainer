<?php

namespace Dashtainer\Domain\Docker\ServiceWorker;

use Dashtainer\Entity\Docker as Entity;
use Dashtainer\Form\Docker as Form;
use Dashtainer\Repository\Docker as Repository;

class Apache extends WorkerAbstract implements WorkerServiceRepoInterface
{
    public const SERVICE_TYPE_SLUG = 'apache';

    /** @var Form\Service\ApacheCreate */
    protected $form;

    /** @var Repository\Service */
    protected $repo;

    public function setRepo(Repository\Service $repo)
    {
        $this->repo = $repo;
    }

    public static function getFormInstance() : Form\Service\CreateAbstract
    {
        return new Form\Service\ApacheCreate();
    }

    public function create()
    {
        $serverNames = array_merge([$this->form->server_name], $this->form->server_alias);
        $serverNames = implode(',', $serverNames);

        $this->service->setName($this->form->name)
            ->setImage('httpd:2.4-alpine')
            ->addLabel('traefik.backend', '{$COMPOSE_PROJECT_NAME}_' . $this->service->getName())
            ->addLabel('traefik.docker.network', 'traefik_webgateway')
            ->addLabel('traefik.frontend.rule', "Host:{$serverNames}")
            ->addLabel('traefik.port', 8080);

        $vhost = [
            'server_name'   => $this->form->server_name,
            'server_alias'  => $this->form->server_alias,
            'document_root' => $this->form->document_root,
            'handler'       => $this->form->handler,
        ];

        $vhostMeta = new Entity\ServiceMeta();
        $vhostMeta->setName('vhost')
            ->setData($vhost)
            ->setService($this->service);
    }

    public function update()
    {
        $serverNames = array_merge([$this->form->server_name], $this->form->server_alias);
        $serverNames = implode(',', $serverNames);

        $this->service->addLabel('traefik.frontend.rule', "Host:{$serverNames}");

        $vhost = [
            'server_name'   => $this->form->server_name,
            'server_alias'  => $this->form->server_alias,
            'document_root' => $this->form->document_root,
            'handler'       => $this->form->handler,
        ];

        $vhostMeta = $this->service->getMeta('vhost');
        $vhostMeta->setData($vhost);
    }

    public function getCreateParams() : array
    {
        return [
            'vhost'         => [
                'server_name'   => 'awesome.localhost',
                'server_alias'  => ['www.awesome.localhost'],
                'document_root' => '/var/www',
                'handler'       => '',
            ],
            'handlers'      => $this->getHandlersForView(),
            'fileHighlight' => 'apacheconf',
        ];
    }

    public function getViewParams() : array
    {
        $vhostMeta = $this->service->getMeta('vhost');

        return [
            'vhost'         => [
                'server_name'   => $vhostMeta->getData()['server_name'],
                'server_alias'  => $vhostMeta->getData()['server_alias'],
                'document_root' => $vhostMeta->getData()['document_root'],
                'handler'       => $vhostMeta->getData()['handler'],
            ],
            'handlers'      => $this->getHandlersForView(),
            'fileHighlight' => 'apacheconf',
        ];
    }

    protected function getHandlersForView() : array
    {
        $project = $this->service->getProject();

        $phpFpmServices = $this->repo->findByProjectAndTypeName(
            $project,
            PhpFpm::SERVICE_TYPE_SLUG
        );

        $phpfpm = [];
        foreach ($phpFpmServices as $service) {
            $phpfpm []= "{$service->getName()}:9000";
        }

        $nodeJsServices = $this->repo->findByProjectAndTypeName(
            $project,
            NodeJs::SERVICE_TYPE_SLUG
        );

        $nodejs = [];
        foreach ($nodeJsServices as $service) {
            $portMeta = $service->getMeta('port');
            $port = $portMeta->getData()[0];

            $nodejs []= "{$service->getName()}:{$port}";
        }

        return [
            'PHP-FPM' => $phpfpm,
            'Node.js' => $nodejs,
        ];
    }

    public function getInternalNetworks() : array
    {
        return [
            'public',
        ];
    }

    public function getInternalVolumes() : array
    {
        return [
            'files' => [
                'httpd-conf',
                'vhost-conf',
            ],
            'other' => [
                'root',
            ],
        ];
    }
}
