<?php


namespace Blog\Controllers;


use Blog\Entities\Skill;
use Blog\Entities\SocialNetwork;
use Blog\Entities\User;
use Blog\Services\FilesService;
use Core\Auth;
use Core\Config;
use Core\Controller;
use Core\HTTPResponse;

class Ajax extends Controller
{

    /**
     * Before filter
     *
     */
    protected function before()
    {
        if ($this->httpRequest->method() !== 'POST' || !$this->httpRequest->isAjax())
        {
            throw new \Exception('not found', 404);
        }
    }

    /**
     * After filter
     *
     */
    protected function after()
    {
        //echo '<p>(after)</p>';
    }


    /**
     * get skills
     */
    public function typedElementsAction()
    {
        $manager = $this->managers->getManagerOf('skill');
        $skills = $manager->getListByBlog();

        $elements = [];
        foreach ($skills as $skill) {
            $elements[]=$skill['value'];
        }

        echo json_encode($elements);
    }


    /**
     * Delete social network
     */
    public function deleteSocialNetworkAction()
    {
        $blogId = 1;
        $config = Config::getInstance();
        if ($config->get('blog_id')) {
            $blogId = $config->get('blog_id');
        }

        $handle = [
            'success' => true,
            'errors' => [],
        ];

        $manager = $this->managers->getManagerOf('SocialNetwork');

        $oldSocialNetwork =  $manager->getUnique($this->httpRequest->postData('id'));

        if (!$oldSocialNetwork || $oldSocialNetwork->getBlogId() != $blogId) {
            $handle['success'] = false;
            $handle['errors'][] = 'Le réseau social à supprimer est invalide.';
            echo json_encode($handle);
            exit();
        }

        if (!$manager->delete($oldSocialNetwork->getId())) {
            $handle['success'] = false;
            $handle['errors'][] = 'Error lors de la suppression du réseau social de la base de données.';
            echo json_encode($handle);
            exit();
        }

        $uploader = new FilesService();
        $iconRules = [
            'target' => 'icons',
            'folder' => '/' . $blogId
        ];

        if (!$uploader->deleteFile($iconRules, $oldSocialNetwork->getLogo())) {
            $manager->save($oldSocialNetwork);
            $handle['success'] = false;
            $handle['errors'][] = 'Error lors de la suppression du logo du réseau social.';
            echo json_encode($handle);
            exit();
        }

        $handle['deleted'] = $oldSocialNetwork->getId();
        echo json_encode($handle);
    }


    /**
     * Save social network
     */
    public function saveSocialNetworkAction()
    {

        $blogId = '1';
        $config = Config::getInstance();
        if ($config->get('blog_id')) {
            $blogId = $config->get('blog_id');
        }

        $logoUploadRules = [
            'target' => 'icons',
            'folder' => '/' . $blogId,
            'old' => $this->httpRequest->postData('old_logo'),
            'maxSize' => 1,
            'type' => 'image',
            'minRes' => [64, 64],
            'maxRes' => [256, 256]
        ];

        $handle = [
            'success' => true,
            'form_errors' => [],
            'errors' => []
        ];

        $socialNetwork = new SocialNetwork([
            'blogId' => $blogId,
            'name' => $this->httpRequest->postData('name'),
            'url' => $this->httpRequest->postData('url')
        ]);

        if ($this->httpRequest->postExists('id')) {
            $socialNetwork->setId($this->httpRequest->postData('id'));
        }

        $handle['form_errors'] = $socialNetwork->getErrors();

        if (!empty($handle['form_errors'])) {
            $handle['success'] = false;
            $handle['errors'][] = 'Erreur dans le formulaire.';
            echo json_encode($handle);
            exit();
        }

        $manager = $this->managers->getManagerOf('SocialNetwork');

        // Vérification qu'aucun autre réseau du blog courant ne porte le même nom
        $double = $manager->doubleExists($socialNetwork);
        if ($double) {
            $handle['errors'][] = "Un autre réseau social porte déjà ce nom.";
            $handle['success'] = false;
            echo json_encode($handle);
            exit();
        }

        if ($this->httpRequest->postExists('id')) {

            // Création du nom de l'icone
            if (!empty($this->httpRequest->filesData('logo')['name'])) {
                $ext = pathinfo($this->httpRequest->filesData('logo')['name'], PATHINFO_EXTENSION);
                $socialNetwork->setLogo($socialNetwork->getName() . '.' . $ext);
            } else {
                $ext = pathinfo($this->httpRequest->postData('old_logo'), PATHINFO_EXTENSION);
                $socialNetwork->setLogo($socialNetwork->getName() . '.' . $ext);
            }

            $oldSocialNetwork =  $manager->getUnique($socialNetwork->getId());

            // Enregistrement du réseau social
             if (!$manager->save($socialNetwork)) {
                 $handle['success'] = false;
                 $handle['errors'][] = "Erreur lors de l'enregistrement.";
                 echo json_encode($handle);
                 exit();
             }

            $uploader = new FilesService();

             // Enregistrement de l'icone si elle a changé
            if (!empty($this->httpRequest->filesData('logo')['name'])) {
                $upload = $uploader->upload($this->httpRequest->filesData('logo'), $logoUploadRules, $socialNetwork->getName());

                if (!$upload['success']) {
                    $manager->save($oldSocialNetwork);
                    $handle['success'] = false;
                    $handle['errors'][] = $upload['errors'];
                    echo json_encode($handle);
                    exit();
                }
            }

            // Renommage de l'icone si le nom du réseau a changé
            if (($oldSocialNetwork->getName() !== $socialNetwork->getName()) && empty($this->httpRequest->filesData('logo')['name'])) {
                $oldPath = $oldSocialNetwork->getLogo();
                $ext = pathinfo($oldPath, PATHINFO_EXTENSION);
                $newPath = $socialNetwork->getName() . '.' . $ext;

                if (!$uploader->rename($logoUploadRules, $oldPath, $newPath)){
                    $manager->save($oldSocialNetwork);
                    $handle['errors'][] = "Impossible de renommer le fichier.";
                    $handle['success'] = false;
                    echo json_encode($handle);
                    exit();
                }
            }
        } else {
            if (empty($this->httpRequest->filesData('logo')['name'])) {
                $handle['errors'][] = "Le logo est manquant.";
                $handle['success'] = false;
                echo json_encode($handle);
                exit();
            }

            $ext = pathinfo($this->httpRequest->filesData('logo')['name'], PATHINFO_EXTENSION);
            $socialNetwork->setLogo($socialNetwork->getName() . '.' . $ext);

            // Enregistrement du réseau social
            $socialNetwork = $manager->save($socialNetwork);
            if (!$socialNetwork) {
                $handle['success'] = false;
                $handle['errors'][] = "Erreur lors de l'enregistrement.";
                echo json_encode($handle);
                exit();
            }

            $uploader = new FilesService();

            // Enregistrement de l'icone
            $upload = $uploader->upload($this->httpRequest->filesData('logo'), $logoUploadRules, $socialNetwork->getName());

            if (!$upload['success']) {
                $manager->delete($socialNetwork->getId());
                $handle['success'] = false;
                $handle['errors'][] = $upload['errors'];
                echo json_encode($handle);
                exit();
            }
        }

        $handle['entity'] = $socialNetwork;

        echo json_encode($handle);
    }


    /**
     * Save skill
     */
    public function saveSkillAction()
    {
        //var_dump($_POST);

        $blogId = '1';
        $config = Config::getInstance();
        if ($config->get('blog_id')) {
            $blogId = $config->get('blog_id');
        }

        $handle = [
            'success' => true,
            'form_errors' => [],
            'errors' => []
        ];

        $skill = new Skill([
            'blogId' => $blogId,
            'value' => $this->httpRequest->postData('skill')
        ]);

        if ($this->httpRequest->postExists('id')) {
            $skill->setId($this->httpRequest->postData('id'));
        }

        $handle['form_errors'] = $skill->getErrors();

        if (!empty($handle['form_errors'])) {
            $handle['success'] = false;
            $handle['errors'][] = 'Erreur dans le formulaire.';
            echo json_encode($handle);
            exit();
        }

        $manager = $this->managers->getManagerOf('Skill');

        // Vérification qu'aucun autre skill du blog courant ne porte le même nom
        $double = $manager->doubleExists($skill);
        if ($double) {
            $handle['errors'][] = "Un autre skill porte déjà ce nom.";
            $handle['success'] = false;
            echo json_encode($handle);
            exit();
        }

        // Enregistrement du skill
         if (!$manager->save($skill)) {
             $handle['success'] = false;
             $handle['errors'][] = "Erreur lors de l'enregistrement.";
             echo json_encode($handle);
             exit();
         }

        $handle['entity'] = $skill;

         //var_dump($skill);

        echo json_encode($handle);
    }

    /**
     * Delete skill
     */
    public function deleteSkillAction()
    {
        $blogId = 1;
        $config = Config::getInstance();
        if ($config->get('blog_id')) {
            $blogId = $config->get('blog_id');
        }

        $handle = [
            'success' => true,
            'errors' => [],
        ];

        $manager = $this->managers->getManagerOf('Skill');

        $oldSkill =  $manager->getUnique($this->httpRequest->postData('id'));

        if (!$oldSkill || $oldSkill->getBlogId() != $blogId) {
            $handle['success'] = false;
            $handle['errors'][] = 'Le skill à supprimer est invalide.';
            echo json_encode($handle);
            exit();
        }

        if (!$manager->delete($oldSkill->getId())) {
            $handle['success'] = false;
            $handle['errors'][] = 'Error lors de la suppression du skill.';
            echo json_encode($handle);
            exit();
        }

        $handle['deleted'] = $oldSkill->getId();
        echo json_encode($handle);
    }

    /**
     * Delete post
     */
    public function deletePostAction()
    {
        $user = Auth::getUser();

        $handle = [
            'success' => true,
            'errors' => [],
        ];

        $manager = $this->managers->getManagerOf('BlogPost');

        $oldPost =  $manager->getUnique($this->httpRequest->postData('id'));

        //var_dump($oldPost->getUser() != $user, $oldPost->getUser(), $user);

        if (!$oldPost || $oldPost->getUser() != $user || $user->getRole() !== 'ROLE_ADMIN') {
            $handle['success'] = false;
            $handle['errors'][] = 'Vous ne pouvez pas supprimer ce post.';
            echo json_encode($handle);
            exit();
        }

        $deleteRules = [
            "target" => "blog",
            "folder" => "",
        ];
        $deleter = new FilesService();
        $dir = "uploads/blog/" . $oldPost->getId();
        if (!$deleter->deleteDirectory($dir)) {
            $handle['success'] = false;
            $handle['errors'][] = 'Error lors de la suppression des fichiers du post.';
            echo json_encode($handle);
            exit();
        }

        if (!$manager->delete($oldPost->getId())) {
            $handle['success'] = false;
            $handle['errors'][] = 'Error lors de la suppression du post.';
            echo json_encode($handle);
            exit();
        }

        $handle['deleted'] = $oldPost->getId();
        echo json_encode($handle);
    }
}