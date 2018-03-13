<?php

namespace Dashtainer\Controller\Project\Service;

use Dashtainer\Entity;
use Dashtainer\Form;
use Dashtainer\Repository;
use Dashtainer\Response\AjaxResponse;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;

class Apache extends Controller
{
    /** @var Repository\Docker\Project */
    protected $repoDockProject;

    public function __construct(
        Repository\Docker\Project $repoDockProject
    ) {
        $this->repoDockProject = $repoDockProject;
    }

    /**
     * @Route(name="project.service.apache.vhost.post",
     *     path="/project/{projectId}/service/apache/vhost/{type}",
     *     methods={"POST"}
     * )
     * @param Request     $request,
     * @param Entity\User $user
     * @param string      $projectId
     * @param string      $type
     * @return AjaxResponse
     */
    public function postVhost(
        Request $request,
        Entity\User $user,
        string $projectId,
        string $type
    ) : AjaxResponse {
        if (!$project = $this->repoDockProject->findByUser($user, $projectId)) {
            return new AjaxResponse([
                'type' => AjaxResponse::AJAX_REDIRECT,
                'data' => '',
            ], AjaxResponse::HTTP_BAD_REQUEST);
        }

        $form = new Form\Docker\Service\ApacheVhost();
        $form->fromArray($request->request->all());

        try {
            $template = "@Dashtainer/project/service/apache/vhost-{$type}.conf.twig";
            $content  = $this->renderView($template, [
                'form' => $form,
            ]);
        } catch (\Exception $e) {
            $content = '';
        }

        return new AjaxResponse([
            'type' => AjaxResponse::AJAX_SUCCESS,
            'data' => $content,
        ], AjaxResponse::HTTP_OK);
    }
}
