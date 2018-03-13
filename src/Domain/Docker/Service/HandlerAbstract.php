<?php

namespace Dashtainer\Domain\Docker\Service;

use Dashtainer\Entity;
use Dashtainer\Form;
use Dashtainer\Repository;
use Dashtainer\Util;
use Dashtainer\Validator\Constraints;

abstract class HandlerAbstract implements HandlerInterface
{
    /** @var Repository\Docker\Service */
    protected $repoDockService;

    public function delete(Entity\Docker\Service $service)
    {
        $metas = [];
        foreach ($service->getMetas() as $meta) {
            $service->removeMeta($meta);

            $metas []= $meta;
        }

        $volumes = [];
        foreach ($service->getVolumes() as $volume) {
            $service->removeVolume($volume);

            $volumes []= $volume;
        }

        if ($parent = $service->getParent()) {
            $service->setParent(null);
            $parent->removeChild($service);

            $this->repoDockService->save($parent);
        }

        $children = [];
        foreach ($service->getChildren() as $child) {
            $child->setParent(null);
            $service->removeChild($child);

            $children []= $child;
        }

        $this->repoDockService->delete(...$metas, ...$volumes, ...$children);
        $this->repoDockService->delete($service);
    }

    /**
     * @param Entity\Docker\Service       $service
     * @param Constraints\CustomFileTrait $form
     */
    protected function customFilesCreate(
        Entity\Docker\Service $service,
        $form
    ) {
        $files = [];
        foreach ($form->custom_file as $fileConfig) {
            $name = Util\Strings::filename($fileConfig['filename']);

            $file = new Entity\Docker\ServiceVolume();
            $file->setName($name)
                ->setSource("\$PWD/{$service->getSlug()}/{$name}")
                ->setTarget($fileConfig['target'])
                ->setData($fileConfig['data'])
                ->setConsistency(Entity\Docker\ServiceVolume::CONSISTENCY_DELEGATED)
                ->setOwner(Entity\Docker\ServiceVolume::OWNER_USER)
                ->setFiletype(Entity\Docker\ServiceVolume::FILETYPE_FILE)
                ->setService($service);

            $service->addVolume($file);

            $files []= $file;
        }

        if (!empty($files)) {
            $this->repoDockService->save($service, ...$files);
        }
    }

    /**
     * @param Entity\Docker\Service       $service
     * @param Constraints\CustomFileTrait $form
     */
    protected function customFilesUpdate(
        Entity\Docker\Service $service,
        $form
    ) {
        $existingUserFiles = $service->getVolumesByOwner(
            Entity\Docker\ServiceVolume::OWNER_USER
        );

        $files = [];
        foreach ($form->custom_file as $id => $fileConfig) {
            $name = Util\Strings::filename($fileConfig['filename']);

            if (empty($existingUserFiles[$id])) {
                $file = new Entity\Docker\ServiceVolume();
                $file->setName($name)
                    ->setSource("\$PWD/{$service->getSlug()}/{$name}")
                    ->setTarget($fileConfig['target'])
                    ->setConsistency(Entity\Docker\ServiceVolume::CONSISTENCY_DELEGATED)
                    ->setData($fileConfig['data'])
                    ->setOwner(Entity\Docker\ServiceVolume::OWNER_USER)
                    ->setFiletype(Entity\Docker\ServiceVolume::FILETYPE_FILE)
                    ->setService($service);

                $service->addVolume($file);

                $files []= $file;

                continue;
            }

            /** @var Entity\Docker\ServiceVolume $file */
            $file = $existingUserFiles[$id];
            unset($existingUserFiles[$id]);

            $file->setName($name)
                ->setSource("\$PWD/{$service->getSlug()}/{$name}")
                ->setTarget($fileConfig['target'])
                ->setData($fileConfig['data']);

            $files []= $file;
        }

        if (!empty($files)) {
            $this->repoDockService->save($service, ...$files);
        }

        foreach ($existingUserFiles as $file) {
            $service->removeVolume($file);
            $this->repoDockService->delete($file);
            $this->repoDockService->save($service);
        }
    }

    /**
     * @param Entity\Docker\Service         $service
     * @param Constraints\ProjectFilesTrait $form
     */
    protected function projectFilesCreate(
        Entity\Docker\Service $service,
        $form
    ) {
        if ($form->project_files['type'] == 'local') {
            $projectFilesMeta = new Entity\Docker\ServiceMeta();
            $projectFilesMeta->setName('project_files')
                ->setData([
                    'type'   => 'local',
                    'source' => $form->project_files['local']['source'],
                ])
                ->setService($service);

            $service->addMeta($projectFilesMeta);

            $projectFilesSource = new Entity\Docker\ServiceVolume();
            $projectFilesSource->setName('project_files_source')
                ->setSource($form->project_files['local']['source'])
                ->setTarget('/var/www')
                ->setConsistency(Entity\Docker\ServiceVolume::CONSISTENCY_CACHED)
                ->setOwner(Entity\Docker\ServiceVolume::OWNER_SYSTEM)
                ->setFiletype(Entity\Docker\ServiceVolume::FILETYPE_DIR)
                ->setService($service);

            $this->repoDockService->save(
                $projectFilesMeta, $projectFilesSource, $service
            );
        }
    }

    /**
     * @param Entity\Docker\Service         $service
     * @param Constraints\ProjectFilesTrait $form
     */
    protected function projectFilesUpdate(
        Entity\Docker\Service $service,
        $form
    ) {
        $projectFilesMeta   = $service->getMeta('project_files');
        $projectFilesSource = $service->getVolume('project_files_source');

        if ($form->project_files['type'] == 'local') {
            $projectFilesMeta->setData([
                'type'   => 'local',
                'source' => $form->project_files['local']['source'],
            ]);

            if (!$projectFilesSource) {
                $projectFilesSource = new Entity\Docker\ServiceVolume();
                $projectFilesSource->setName('project_files_source')
                    ->setTarget('/var/www')
                    ->setConsistency(Entity\Docker\ServiceVolume::CONSISTENCY_CACHED)
                    ->setOwner(Entity\Docker\ServiceVolume::OWNER_SYSTEM)
                    ->setFiletype(Entity\Docker\ServiceVolume::FILETYPE_DIR)
                    ->setService($service);
            }

            $projectFilesSource->setSource($form->project_files['local']['source']);

            $this->repoDockService->save(
                $projectFilesMeta, $projectFilesSource, $service
            );
        }

        if ($form->project_files['type'] !== 'local' && $projectFilesSource) {
            $projectFilesSource->setService(null);
            $service->removeVolume($projectFilesSource);

            $this->repoDockService->delete($projectFilesSource);

            $this->repoDockService->save($service);
        }

        // todo: Add support for non-local project files source, ie github
    }

    protected function projectFilesViewParams(Entity\Docker\Service $service) : array
    {
        $projectFilesMeta = $service->getMeta('project_files');

        $projectFilesLocal = [
            'type'   => 'local',
            'source' => '',
        ];
        if ($projectFilesMeta->getData()['type'] == 'local') {
            $projectFilesLocal['source'] = $projectFilesMeta->getData()['source'];
        }

        return [
            'type'  => $projectFilesMeta->getData()['type'],
            'local' => $projectFilesLocal,
        ];
    }
}
