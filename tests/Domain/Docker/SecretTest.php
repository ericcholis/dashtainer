<?php

namespace Dashtainer\Tests\Domain\Docker;

use Dashtainer\Domain\Docker\Secret;
use Dashtainer\Entity\Docker as Entity;
use Dashtainer\Tests\Mock;

class SecretTest extends DomainAbstract
{
    /** @var Secret */
    protected $secret;

    protected function setUp()
    {
        $this->secret = new Secret(new Mock\RepoDockerSecret($this->getEm()));
    }

    public function testDeleteAllForService()
    {
        $projectSecretA = $this->createProjectSecret('project-secret-a');
        $projectSecretB = $this->createProjectSecret('project-secret-b');
        $projectSecretC = $this->createProjectSecret('project-secret-c');

        $serviceSecretA = $this->createServiceSecret('service-secret-a');
        $serviceSecretB = $this->createServiceSecret('service-secret-b');
        $serviceSecretC = $this->createServiceSecret('service-secret-c');
        $serviceSecretD = $this->createServiceSecret('service-secret-d');
        $serviceSecretE = $this->createServiceSecret('service-secret-e');
        $serviceSecretF = $this->createServiceSecret('service-secret-f');
        $serviceSecretG = $this->createServiceSecret('service-secret-g');

        $serviceA = $this->createService('service-a');
        $serviceB = $this->createService('service-b');

        $project = $this->createProject('project');
        $project->addService($serviceA)
            ->addService($serviceB)
            ->addSecret($projectSecretA)
            ->addSecret($projectSecretB)
            ->addSecret($projectSecretC);

        /*
         * Owned, granted to other Services
         *
         * projectSecretA -> serviceA
         *      serviceSecretA
         *          serviceA
         *      serviceSecretB
         *          serviceB
         */
        $serviceA->addSecret($serviceSecretA);
        $serviceB->addSecret($serviceSecretB);
        $projectSecretA->addServiceSecret($serviceSecretA)
            ->addServiceSecret($serviceSecretB)
            ->setOwner($serviceA);

        /*
         * Owned, not granted to other Services
         *
         * projectSecretB -> serviceA
         *      serviceSecretC
         *          serviceA
         */
        $serviceA->addSecret($serviceSecretC);
        $projectSecretB->addServiceSecret($serviceSecretC)
            ->setOwner($serviceA);

        /*
         * Not owned, granted
         *
         * projectSecretC -> serviceB
         *      serviceSecretD
         *          serviceB
         *      serviceSecretE
         *          serviceA
         */
        $serviceB->addSecret($serviceSecretD);
        $serviceA->addSecret($serviceSecretE);
        $projectSecretC->addServiceSecret($serviceSecretD)
            ->addServiceSecret($serviceSecretE)
            ->setOwner($serviceB);

        /*
         * No ProjectSecret, only ServiceSecret
         *
         * serviceSecretF
         *      serviceA
         */
        $serviceA->addSecret($serviceSecretF);

        /*
         * Not owned by or granted to Service
         *
         * serviceSecretG
         *      serviceB
         */
        $serviceB->addSecret($serviceSecretG);

        $this->secret->deleteAllForService($serviceA);

        $this->assertEmpty($serviceA->getSecrets());
        $this->assertFalse($serviceB->getSecrets()->contains($serviceSecretB));

        $this->assertNull($serviceSecretA->getService());
        $this->assertNull($serviceSecretA->getProjectSecret());
        $this->assertNull($serviceSecretB->getService());
        $this->assertNull($serviceSecretB->getProjectSecret());

        $this->assertNull($serviceSecretC->getService());
        $this->assertNull($serviceSecretC->getProjectSecret());

        $this->assertNull($serviceSecretE->getService());
        $this->assertNull($serviceSecretE->getProjectSecret());
        $this->assertFalse($projectSecretC->getServiceSecrets()->contains($serviceSecretE));

        $this->assertNull($serviceSecretF->getService());

        $this->assertSame($serviceB, $serviceSecretD->getService());
        $this->assertSame($serviceB, $serviceSecretG->getService());
        $this->assertSame($serviceB, $projectSecretC->getOwner());
        $this->assertSame($projectSecretC, $serviceSecretD->getProjectSecret());
    }

    public function testGetForNewServiceReturnsSecrets()
    {
        $grantableProjectSecretA    = $this->createProjectSecret('project-secret-a');
        $notGrantableProjectSecretB = $this->createProjectSecret('project-secret-b');

        $grantableServiceSecretA    = $this->createServiceSecret('service-secret-a');
        $notGrantableServiceSecretB = $this->createServiceSecret('service-secret-b');

        $serviceA = $this->createService('service-a');

        $project = $this->createProject('project');
        $project->addService($serviceA)
            ->addSecret($grantableProjectSecretA);

        // Grantable: ServiceSecret with ProjectSecret, owned by Service
        $serviceA->addSecret($grantableServiceSecretA);
        $grantableProjectSecretA->addServiceSecret($grantableServiceSecretA)
            ->setOwner($serviceA);

        // Not Grantable: ServiceSecret with ProjectSecret, not owned by Service
        $serviceA->addSecret($notGrantableServiceSecretB);
        $notGrantableProjectSecretB->addServiceSecret($notGrantableServiceSecretB);

        $serviceType = $this->createServiceType('service-type');

        $metaSecretA = $this->createServiceTypeMeta('internal_secret_a');
        $metaSecretA->setData([
            'name' => 'internal_secret_a',
            'data' => 'internal_secret_a data',
        ]);

        $metaSecretB = $this->createServiceTypeMeta('internal_secret_b');
        $metaSecretB->setData([
            'name' => 'internal_secret_b',
            'data' => 'internal_secret_b data',
        ]);

        $serviceType->addMeta($metaSecretA)
            ->addMeta($metaSecretB);

        $internalSecretsArray = [
            'internal_secret_a',
            'internal_secret_b',
        ];

        $result = $this->secret->getForNewService($project, $serviceType, $internalSecretsArray);

        $owned     = $result['owned'];
        $granted   = $result['granted'];
        $grantable = $result['grantable'];

        /** @var Entity\ServiceSecret $internalServiceSecretA */
        $internalServiceSecretA = $owned->get('internal_secret_a');
        $internalProjectSecretA = $internalServiceSecretA->getProjectSecret();

        /** @var Entity\ServiceSecret $internalServiceSecretB */
        $internalServiceSecretB = $owned->get('internal_secret_b');
        $internalProjectSecretB = $internalServiceSecretB->getProjectSecret();

        $this->assertEquals('internal_secret_a', $internalServiceSecretA->getName());
        $this->assertEquals('internal_secret_a data', $internalProjectSecretA->getData());

        $this->assertEquals('internal_secret_b', $internalServiceSecretB->getName());
        $this->assertEquals('internal_secret_b data', $internalProjectSecretB->getData());

        $this->assertEmpty($granted->count());

        $this->assertTrue($grantable->containsKey($grantableProjectSecretA->getName()));
        $this->assertSame($grantableServiceSecretA, $grantable->get($grantableProjectSecretA->getName()));
    }

    public function testGetForExistingService()
    {
        $grantedProjectSecretA   = $this->createProjectSecret('project-secret-a');
        $grantableProjectSecretB = $this->createProjectSecret('project-secret-b');

        $grantedServiceSecretA   = $this->createServiceSecret('service-secret-a');
        $grantableServiceSecretB = $this->createServiceSecret('service-secret-b');

        $serviceA = $this->createService('service-a');

        $project = $this->createProject('project');
        $project->addService($serviceA)
            ->addSecret($grantedProjectSecretA)
            ->addSecret($grantableProjectSecretB);

        // Grantable, to be Granted: ServiceSecret with ProjectSecret, owned by Service
        $serviceA->addSecret($grantedServiceSecretA);
        $grantedServiceSecretA->setIsInternal(true);
        $grantedProjectSecretA->addServiceSecret($grantedServiceSecretA)
            ->setOwner($serviceA)
            ->setData('project secret a data');

        // Grantable, not Granted: ServiceSecret with ProjectSecret, owned by Service
        $serviceA->addSecret($grantableServiceSecretB);
        $grantableServiceSecretB->setIsInternal(true);
        $grantableProjectSecretB->addServiceSecret($grantableServiceSecretB)
            ->setOwner($serviceA)
            ->setData('project secret b data');

        $serviceType = $this->createServiceType('service-type');

        $metaSecretA = $this->createServiceTypeMeta('internal_secret_a');
        $metaSecretA->setData([
            'name' => 'internal_secret_a',
            'data' => 'internal_secret_a data',
        ]);

        $metaSecretB = $this->createServiceTypeMeta('internal_secret_b');
        $metaSecretB->setData([
            'name' => 'internal_secret_b',
            'data' => 'internal_secret_b data',
        ]);

        $serviceType->addMeta($metaSecretA)
            ->addMeta($metaSecretB);

        ///////

        $internalProjectSecretA = $this->createProjectSecret('internal_secret_a-project_secret');
        $ownedProjectSecretA    = $this->createProjectSecret('owned_secret');

        $internalServiceSecretA  = $this->createServiceSecret('internal_secret_a');
        $grantedServiceSecretA_A = $this->createServiceSecret('service-secret-a-granted');
        $ownedServiceSecret      = $this->createServiceSecret('owned_secret');

        $currentService = $this->createService('current-service');
        $project->addService($currentService);

        // Owned, grantable
        $currentService->addSecret($internalServiceSecretA);
        $internalServiceSecretA->setIsInternal(true);
        $internalProjectSecretA->addServiceSecret($internalServiceSecretA)
            ->setOwner($currentService)
            ->setData('user defined data for internal secret');

        $currentService->addSecret($ownedServiceSecret);
        $ownedProjectSecretA->addServiceSecret($ownedServiceSecret)
            ->setOwner($currentService)
            ->setData('user defined data for owned secret');

        // Not owned, granted
        $currentService->addSecret($grantedServiceSecretA_A);
        $grantedProjectSecretA->addServiceSecret($grantedServiceSecretA_A);

        $internalSecretsArray = [
            'internal_secret_a',
            'internal_secret_b',
            'fake-meta',
        ];

        $result = $this->secret->getForExistingService($currentService, $serviceType, $internalSecretsArray);

        $owned     = $result['owned'];
        $granted   = $result['granted'];
        $grantable = $result['grantable'];

        $this->assertEquals(3, $owned->count());
        /** @var Entity\ServiceSecret $resultOwnedServiceSecretA */
        $resultOwnedServiceSecretA = $owned->get('internal_secret_a');
        /** @var Entity\ServiceSecret $resultOwnedServiceSecretB */
        $resultOwnedServiceSecretB = $owned->get('internal_secret_b');
        /** @var Entity\ServiceSecret $resultOwnedServiceSecretC */
        $resultOwnedServiceSecretC = $owned->get('owned_secret');

        $this->assertSame($internalServiceSecretA, $resultOwnedServiceSecretA);
        $this->assertSame($internalProjectSecretA, $resultOwnedServiceSecretA->getProjectSecret());

        $resultOwnedProjectSecretB = $resultOwnedServiceSecretB->getProjectSecret();

        $this->assertEquals('internal_secret_b', $resultOwnedServiceSecretB->getName());
        $this->assertEquals('internal_secret_b data', $resultOwnedProjectSecretB->getData());

        $this->assertSame($ownedServiceSecret, $resultOwnedServiceSecretC);
        $this->assertSame($ownedProjectSecretA, $resultOwnedServiceSecretC->getProjectSecret());

        $this->assertEquals(1, $granted->count());
        /** @var Entity\ServiceSecret $resultGrantedServiceSecret */
        $resultGrantedServiceSecret = $granted->get('project-secret-a');
        $this->assertSame($grantedProjectSecretA, $resultGrantedServiceSecret->getProjectSecret());

        $this->assertEquals(1, $grantable->count());
        /** @var Entity\ServiceSecret $resultGrantableServiceSecret */
        $resultGrantableServiceSecret = $grantable->get('project-secret-b');
        $this->assertSame($grantableProjectSecretB, $resultGrantableServiceSecret->getProjectSecret());
    }

    public function testSaveUpdatesAndCreates()
    {
        $project = $this->createProject('project');
        $service = $this->createService('service');

        $project->addService($service);

        $internalServiceSecretA = $this->createServiceSecret('internal-service-secret-a');
        $internalServiceSecretA->setService($service)
            ->setTarget('internal-service-secret-a target')
            ->setIsInternal(true);
        $internalProjectSecretA = $this->createProjectSecret('internal-project-secret-a');
        $internalProjectSecretA->setProject($project)
            ->setData('internal-project-secret-a data')
            ->setFile('internal-project-secret-a file')
            ->addServiceSecret($internalServiceSecretA)
            ->setOwner($service);

        $internalServiceSecretB = $this->createServiceSecret('internal-service-secret-b');
        $internalServiceSecretB->setService($service)
            ->setTarget('internal-service-secret-b target')
            ->setIsInternal(true);
        $internalProjectSecretB = $this->createProjectSecret('internal-project-secret-b');
        $internalProjectSecretB->setProject($project)
            ->setData('internal-project-secret-b data')
            ->setFile('internal-project-secret-b file')
            ->addServiceSecret($internalServiceSecretB)
            ->setOwner($service);

        ////

        $notInternalServiceSecret = $this->createServiceSecret('not-internal-service-secret');
        $notInternalServiceSecret->setService($service)
            ->setTarget('not-internal-service-secret target')
            ->setIsInternal(false);
        $notInternalProjectSecret = $this->createProjectSecret('not-internal-project-secret');
        $notInternalProjectSecret->setProject($project)
            ->setData('not-internal-project-secret data')
            ->setFile('not-internal-project-secret file')
            ->addServiceSecret($notInternalServiceSecret)
            ->setOwner($service);

        $deleteServiceSecret = $this->createServiceSecret('delete-service-secret');
        $deleteServiceSecret->setService($service)
            ->setTarget('not-internal-service-secret target')
            ->setIsInternal(false);
        $deleteProjectSecret = $this->createProjectSecret('delete-project-secret');
        $deleteProjectSecret->setProject($project)
            ->setData('delete-project-secret data')
            ->setFile('delete-project-secret file')
            ->addServiceSecret($deleteServiceSecret)
            ->setOwner($service);

        $serviceB = $this->createService('service-b');
        $project->addService($serviceB);

        $serviceBDeleteSecret = $this->createServiceSecret('service-b-delete-secret');
        $deleteProjectSecret->addServiceSecret($serviceBDeleteSecret);

        $internalSecrets = [
            $internalServiceSecretA,
            $internalServiceSecretB,
            $notInternalServiceSecret,
        ];

        $configs = [
            'internal-service-secret-a'   => [
                'name' => 'internal-service-secret-a new name',
                'data' => 'internal-service-secret-a new data',
            ],
            'internal-service-secret-b'   => [
                'name' => 'internal-service-secret-b new name',
                'data' => 'internal-service-secret-b new data',
            ],
            'not-internal-service-secret' => [
                'name' => 'not-internal-service-secret new name',
                'data' => 'not-internal-service-secret new data',
            ],
            'new-secret'                  => [
                'name' => 'new-secret name',
                'data' => 'new-secret data',
            ],
        ];

        $this->secret->save($service, $internalSecrets, $configs);

        $this->assertFalse($service->getSecrets()->contains($deleteServiceSecret));
        $this->assertNull($deleteServiceSecret->getProjectSecret());
        $this->assertCount(0, $deleteProjectSecret->getServiceSecrets());

        $this->assertTrue($service->getSecrets()->contains($internalServiceSecretA));
        $this->assertTrue($service->getSecrets()->contains($internalServiceSecretB));

        $this->assertEquals('internal-service-secret-a new data', $internalProjectSecretA->getData());
        $this->assertEquals('internal-service-secret-b new data', $internalProjectSecretB->getData());

        $this->assertTrue($service->getSecrets()->contains($notInternalServiceSecret));
        $this->assertEquals('not-internal-service-secret new data', $notInternalProjectSecret->getData());

        $service->getSecrets()->removeElement($internalServiceSecretA);
        $service->getSecrets()->removeElement($internalServiceSecretB);
        $service->getSecrets()->removeElement($notInternalServiceSecret);

        /** @var Entity\ServiceSecret $newSeviceSecret */
        $newSeviceSecret = $service->getSecrets()->first();
        $newProjectSecret = $newSeviceSecret->getProjectSecret();
        $this->assertEquals('new-secret data', $newProjectSecret->getData());
    }

    public function testGrantRemovesSecretsNoLongerGranted()
    {
        $project = $this->createProject('project');

        $serviceA = $this->createService('service-a')
            ->setProject($project);

        $serviceSecretA = $this->createServiceSecret('service-secret-a');
        $serviceSecretA->setService($serviceA)
            ->setTarget('service-secret-a target');
        $projectSecretA = $this->createProjectSecret('project-secret-a');
        $projectSecretA->setProject($project)
            ->setData('project-secret-a data')
            ->setFile('project-secret-a file')
            ->addServiceSecret($serviceSecretA)
            ->setOwner($serviceA);

        $serviceB = $this->createService('service-b')
            ->setProject($project);

        $serviceSecretAGrant = $this->createServiceSecret('service-secret-a-grant');
        $serviceSecretAGrant->setService($serviceB)
            ->setTarget('service-secret-a-grant target');
        $projectSecretA->addServiceSecret($serviceSecretAGrant);

        $toGrant = [];

        $this->secret->grant($serviceB, $toGrant);

        $this->assertCount(0, $serviceB->getSecrets());
    }

    public function testGrantAddsSecretsToService()
    {
        $project = $this->createProject('project');

        $serviceA = $this->createService('service-a')
            ->setProject($project);

        $serviceSecretA = $this->createServiceSecret('service-secret-a');
        $serviceSecretA->setService($serviceA)
            ->setTarget('service-secret-a target');
        $projectSecretA = $this->createProjectSecret('project-secret-a');
        $projectSecretA->setProject($project)
            ->setData('project-secret-a data')
            ->setFile('project-secret-a file')
            ->addServiceSecret($serviceSecretA)
            ->setOwner($serviceA);

        $serviceSecretB = $this->createServiceSecret('service-secret-b');
        $serviceSecretB->setService($serviceA)
            ->setTarget('service-secret-b target');
        $projectSecretB = $this->createProjectSecret('project-secret-b');
        $projectSecretB->setProject($project)
            ->setData('project-secret-b data')
            ->setFile('project-secret-b file')
            ->addServiceSecret($serviceSecretB)
            ->setOwner($serviceA);

        $serviceB = $this->createService('service-b')
            ->setProject($project);

        $serviceSecretAGrant = $this->createServiceSecret('service-secret-a-grant');
        $serviceSecretAGrant->setService($serviceB)
            ->setTarget('service-secret-a-grant target');
        $projectSecretA->addServiceSecret($serviceSecretAGrant);

        $toGrant = [
            [
                'id'     => 'project-secret-a',
                'name'   => 'project-secret-a-name',
                'target' => 'project-secret-a-target',
            ],
            [
                'id'     => 'project-secret-b',
                'name'   => 'project-secret-b-name',
                'target' => 'project-secret-b-target',
            ],
            [
                'id'     => null,
                'name'   => '',
                'target' => '',
            ],
            [
                'id'     => 'non-existant-id',
                'name'   => '',
                'target' => '',
            ],
        ];

        $this->secret->grant($serviceB, $toGrant);

        $this->assertFalse($serviceB->getSecrets()->contains($serviceSecretAGrant));

        /** @var Entity\ServiceSecret $grantA */
        $grantA = $serviceB->getSecrets()->first();
        /** @var Entity\ServiceSecret $grantB */
        $grantB = $serviceB->getSecrets()->next();

        $this->assertSame($projectSecretA, $grantA->getProjectSecret());
        $this->assertEquals($grantA->getName(), 'project-secret-a-name');

        $this->assertSame($projectSecretB, $grantB->getProjectSecret());
        $this->assertEquals($grantB->getName(), 'project-secret-b-name');
    }
}
