<?php


namespace Blog\Controllers;


use Blog\Entities\Skill;
use Blog\Entities\SocialNetwork;
use Blog\Entities\User;
use Blog\Services\FilesService;
use Blog\Services\MailService;
use Core\Controller;
use Core\Flash;

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
     * get skills
     */
    public function typedElementsAction()
    {
        $blogId = $this->config->get('blog_id') ? $this->config->get('blog_id') : 1;
        $skillManager = $this->managers->getManagerOf('skill');
        $skills = $skillManager->getListByBlog($blogId);

        $elements = [];
        foreach ($skills as $skill) {
            $elements[]=$skill['value'];
        }

        $this->httpResponse->ajaxResponse($elements);
    }


    /**
     * Delete social network
     */
    public function deleteSocialNetworkAction()
    {
        $this->requiredLogin('admin');
        $blogId = $this->config->get('blog_id') ? $this->config->get('blog_id') : 1;

        $handle = [
            'success' => false,
            'errors' => [],
        ];

        $manager = $this->managers->getManagerOf('SocialNetwork');

        $oldSocialNetwork =  $manager->getUnique($this->httpRequest->postData('id'));

        if (!$oldSocialNetwork || $oldSocialNetwork->getBlogId() != $blogId) {
            $handle['errors'][] = 'Le réseau social à supprimer est invalide.';
            $this->httpResponse->ajaxResponse($handle);
            return;
        }

        if (!$manager->delete($oldSocialNetwork->getId())) {
            $handle['errors'][] = 'Error lors de la suppression du réseau social de la base de données.';
            $this->httpResponse->ajaxResponse($handle);
            return;
        }

        $uploader = new FilesService();
        $iconRules = [
            'target' => 'icons',
            'folder' => '/' . $blogId
        ];

        if (!$uploader->deleteFile($iconRules, $oldSocialNetwork->getLogo())) {
            $manager->save($oldSocialNetwork);
            $handle['errors'][] = 'Error lors de la suppression du logo du réseau social.';
            $this->httpResponse->ajaxResponse($handle);
            return;
        }

        $handle['success'] = true;
        $handle['deleted'] = $oldSocialNetwork->getId();
        $this->httpResponse->ajaxResponse($handle);
    }


    /**
     * Save social network
     */
    public function saveSocialNetworkAction()
    {
        $this->requiredLogin('admin');
        $blogId = $this->config->get('blog_id') ? $this->config->get('blog_id') : 1;

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
            'success' => false,
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
            $handle['errors'][] = 'Erreur dans le formulaire.';
            $this->httpResponse->ajaxResponse($handle);
            return;
        }

        $manager = $this->managers->getManagerOf('SocialNetwork');

        // Vérification qu'aucun autre réseau du blog courant ne porte le même nom
        $double = $manager->doubleExists($socialNetwork);
        if ($double) {
            $handle['errors'][] = "Un autre réseau social porte déjà ce nom.";
            $this->httpResponse->ajaxResponse($handle);
            return;
        }

        if ($this->httpRequest->postExists('id')) {

            // Création du nom de l'icone
            $ext = pathinfo($this->httpRequest->postData('old_logo'), PATHINFO_EXTENSION);
            if (!empty($this->httpRequest->filesData('logo')['name'])) {
                $ext = pathinfo($this->httpRequest->filesData('logo')['name'], PATHINFO_EXTENSION);
            }
            $socialNetwork->setLogo(urlencode($socialNetwork->getName()) . '.' . $ext);

            $oldSocialNetwork =  $manager->getUnique($socialNetwork->getId());

            // Enregistrement du réseau social
             if (!$manager->save($socialNetwork)) {
                 $handle['errors'][] = "Erreur lors de l'enregistrement.";
                 $this->httpResponse->ajaxResponse($handle);
                 return;
             }

            $uploader = new FilesService();

             // Enregistrement de l'icone si elle a changé
            if (!empty($this->httpRequest->filesData('logo')['name'])) {
                $upload = $uploader->upload($this->httpRequest->filesData('logo'), $logoUploadRules, $socialNetwork->getName());

                if (!$upload['success']) {
                    $manager->save($oldSocialNetwork);
                    $handle['errors'][] = $upload['errors'];
                    $this->httpResponse->ajaxResponse($handle);
                    return;
                }
            }

            // Renommage de l'icone si le nom du réseau a changé
            if (($oldSocialNetwork->getLogo() !== $socialNetwork->getLogo()) && empty($this->httpRequest->filesData('logo')['name'])) {
                $oldPath = $oldSocialNetwork->getLogo();
                $newPath = $socialNetwork->getLogo();

                if (!$uploader->rename($logoUploadRules, $oldPath, $newPath)){
                    $manager->save($oldSocialNetwork);
                    $handle['errors'][] = "Impossible de renommer le fichier.";
                    $this->httpResponse->ajaxResponse($handle);
                    return;
                }
            }
        } else {
            if (empty($this->httpRequest->filesData('logo')['name'])) {
                $handle['errors'][] = "Le logo est manquant.";
                $this->httpResponse->ajaxResponse($handle);
                return;
            }

            $ext = pathinfo($this->httpRequest->filesData('logo')['name'], PATHINFO_EXTENSION);
            $socialNetwork->setLogo(urlencode($socialNetwork->getName()) . '.' . $ext);

            // Enregistrement du réseau social
            $socialNetwork = $manager->save($socialNetwork);
            if (!$socialNetwork) {
                $handle['errors'][] = "Erreur lors de l'enregistrement.";
                $this->httpResponse->ajaxResponse($handle);
                return;
            }

            $uploader = new FilesService();

            // Enregistrement de l'icone
            $upload = $uploader->upload($this->httpRequest->filesData('logo'), $logoUploadRules, $socialNetwork->getName());

            if (!$upload['success']) {
                $manager->delete($socialNetwork->getId());
                $handle['errors'][] = $upload['errors'];
                $this->httpResponse->ajaxResponse($handle);
                return;
            }
        }

        $handle['success'] = true;
        $handle['entity'] = $socialNetwork;
        $this->httpResponse->ajaxResponse($handle);
    }


    /**
     * Save skill
     */
    public function saveSkillAction()
    {
        $this->requiredLogin('admin');

        $blogId = $this->config->get('blog_id') ? $this->config->get('blog_id') : 1;

        $handle = [
            'success' => false,
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
            $handle['errors'][] = 'Erreur dans le formulaire.';
            $this->httpResponse->ajaxResponse($handle);
            return;
        }

        $manager = $this->managers->getManagerOf('Skill');

        // Vérification qu'aucun autre skill du blog courant ne porte le même nom
        $double = $manager->doubleExists($skill);
        if ($double) {
            $handle['errors'][] = "Un autre skill porte déjà ce nom.";
            $this->httpResponse->ajaxResponse($handle);
            return;
        }

        // Enregistrement du skill
         if (!$manager->save($skill)) {
             $handle['errors'][] = "Erreur lors de l'enregistrement.";
             $this->httpResponse->ajaxResponse($handle);
             return;
         }

        $handle['success'] = true;
        $handle['entity'] = $skill;
        $this->httpResponse->ajaxResponse($handle);
    }

    /**
     * Delete skill
     */
    public function deleteSkillAction()
    {

        $this->requiredLogin('admin');
        $blogId = $this->config->get('blog_id') ? $this->config->get('blog_id') : 1;

        $handle = [
            'success' => false,
            'errors' => [],
        ];

        $manager = $this->managers->getManagerOf('Skill');

        $oldSkill =  $manager->getUnique($this->httpRequest->postData('id'));

        if (!$oldSkill || $oldSkill->getBlogId() != $blogId) {
            $handle['errors'][] = 'Le skill à supprimer est invalide.';
            $this->httpResponse->ajaxResponse($handle);
            return;
        }

        if (!$manager->delete($oldSkill->getId())) {
            $handle['errors'][] = 'Error lors de la suppression du skill.';
            $this->httpResponse->ajaxResponse($handle);
            return;
        }

        $handle['success'] = true;
        $handle['deleted'] = $oldSkill->getId();
        $this->httpResponse->ajaxResponse($handle);
    }

    /**
     * Delete post
     */
    public function deletePostAction()
    {
        $this->requiredLogin('admin');

        $user = $this->auth->getUser();

        $handle = [
            'success' => false,
            'errors' => [],
        ];

        $manager = $this->managers->getManagerOf('BlogPost');

        $oldPost =  $manager->getUnique($this->httpRequest->postData('id'));

        if (!$oldPost || ($oldPost->getUser()->getId() != $user->getId() && $oldPost->getuser()->isGranted('admin') && !$oldPost->getUser()->getBanished())) {
            $handle['errors'][] = 'Vous ne pouvez pas supprimer ce post.';
            $this->httpResponse->ajaxResponse($handle);
            return;
        }

        $postDelete = $this->entityDeleter($oldPost);
        if ($postDelete !== 'success') {
            $handle['errors'][] = $postDelete;
            $this->httpResponse->ajaxResponse($handle);
            return;
        }

        $handle['success'] = true;
        $handle['deleted'] = $oldPost->getId();
        $this->httpResponse->ajaxResponse($handle);
    }

    /**
     * change Password from profile
     */
    public function changePassword()
    {
        $this->requiredLogin('user');

        $user = $this->auth->getUser();

        $handle = [
            'success' => false,
            'token_error' => false,
            'old_error' => false,
            'new_error' => false,
            'conf_error' => false,
            'db_error' => false,
        ];
        if (!$this->isCsrfTokenValid($this->httpRequest->postData('token'))) {
            $handle['token_error'] = true;
            $this->httpResponse->ajaxResponse($handle);
            return;
        }
        if (!password_verify($this->httpRequest->postData('old_password'), $user->getPassword())) {
            $user->addCustomError('password', 'Le mot de passe actuel est invalide.');
            $handle['old_error'] = true;
        }
        if ($this->httpRequest->postData('new_password') !== $this->httpRequest->postData('conf_password')) {
            $user->addCustomError('password', "Le nouveau mot de passe est différent de sa confirmation.");
            $handle['conf_error'] = true;
        }
        $user->setPlainPassword($this->httpRequest->postData('new_password'));
        foreach ($user->getErrors() as $error) {
            if ($error === User::INVALID_PASSWORD) {
                $handle['new_error'] = true;
                break;
            }
        }
        if ($user->isValid()) {
            $userManager =  $this->managers->getManagerOf('user');
            $user = $userManager->resetPassword($user);
            if ($user) {
                $handle['success'] = true;
                $this->httpResponse->ajaxResponse($handle);
                return;
            }
            $handle['db_error'] = true;
        }

        $this->httpResponse->ajaxResponse($handle);
    }

    /**
     * up user
     */
    public function upUserAction()
    {
        $this->switchRole('ROLE_ADMIN');
    }

    /**
     * down user
     */
    public function downUserAction()
    {
        $this->switchRole('ROLE_USER');
    }

    /**
     * Switch user role
     */
    private function switchRole(string $role) {
        $this->requiredLogin('admin');

        $handle = [
            'success' => false,
            'errors' => [],
        ];

        if (!$this->isCsrfTokenValid($this->httpRequest->postData('token'), false)) {
            $handle['errors'][] = 'Une erreur s\'est produite. Merci d\'actualisez la page et de recommencer.';
            $this->httpResponse->ajaxResponse($handle);
            return;
        }

        $userManager = $this->managers->getManagerOf('user');
        $user =  $userManager->findById($this->httpRequest->postData('id'));

        if ($user->getId() == $this->auth->getUser()->getId()) {
            $handle['errors'][] = 'Vous ne pouvez pas changer votre role.';
            $this->httpResponse->ajaxResponse($handle);
            return;
        }

        $user->setRole($role);

        if (!$user->isValid()) {
            $handle['errors'][] = 'L\'utilisateur est invalide.';
            $this->httpResponse->ajaxResponse($handle);
            return;

        }

        $mailer = new MailService();
        if (!$mailer->sendRoleChangeEmail($user, $this->httpRequest->postData('message_field'))) {
            $handle['errors'][] = 'Erreur lors de l\'envoi du mail.';
            $this->httpResponse->ajaxResponse($handle);
            return;
        }
        if ($userManager->save($user)) {
            $handle['success'] = true;
            $this->flash->addMessage('Le role de l\'utilisateur a bien été modifié.', Flash::SUCCESS);
            $this->httpResponse->ajaxResponse($handle);
            return;
        }
        $handle['errors'][] = 'Error lors de l\'enregistrement.';
        $this->httpResponse->ajaxResponse($handle);
    }

    /**
     * User banish
     */
    public function banishUserAction()
    {
        $this->switchBanished(true);
    }

    /**
     * User unbanish
     */
    public function unbanishUserAction()
    {
        $this->switchBanished(false);
    }

    /**
     * Switch user banished
     * @param bool $banished
     */
    private function switchBanished(bool $banished) {
        $this->requiredLogin('admin');

        $handle = [
            'success' => false,
            'errors' => [],
        ];

        if (!$this->isCsrfTokenValid($this->httpRequest->postData('token'), false)) {
            $handle['errors'][] = 'Une erreur s\'est produite. Merci d\'actualisez la page et de recommencer.';
            $this->httpResponse->ajaxResponse($handle);
            return;
        }

        $userManager = $this->managers->getManagerOf('user');
        $user =  $userManager->findById($this->httpRequest->postData('id'));

        if ($user->getId() == $this->auth->getUser()->getId()) {
            $handle['errors'][] = 'Vous ne pouvez pas changer votre état.';
            $this->httpResponse->ajaxResponse($handle);
            return;
        }

        $user->setBanished($banished);
        if ($user->isValid()) {
            $mailer = new MailService();

            if (!$mailer->sendStatusChangeEmail($user, $this->httpRequest->postData('message_field'))) {
                $handle['errors'][] = 'Erreur lors de l\'envoi du mail.';
                $this->httpResponse->ajaxResponse($handle);
                return;
            }

            if ($this->httpRequest->postData('delete_messages')) {
                $postDelete = $this->entityDeleter($user);
                if ($postDelete !== 'success') {
                    $handle['errors'][] = $postDelete;
                    $this->httpResponse->ajaxResponse($handle);
                    return;
                }
            }

            if ($userManager->save($user)) {
                $handle['success'] = true;
                $this->httpResponse->ajaxResponse($handle);
                return;
            }
            $handle['errors'][] = 'Error lors de l\'enregistrement.';
            $this->httpResponse->ajaxResponse($handle);
            return;
        }
        $handle['errors'][] = 'L\'utilisateur est invalide.';
        $this->httpResponse->ajaxResponse($handle);
    }

    /**
     * User delete
     */
    public function deleteUserAction()
    {
        $this->requiredLogin('admin');

        $handle = [
            'success' => false,
            'errors' => [],
        ];

        if (!$this->isCsrfTokenValid($this->httpRequest->postData('token'), false)) {
            $handle['errors'][] = 'Une erreur s\'est produite. Merci d\'actualisez la page et de recommencer.';
            $this->httpResponse->ajaxResponse($handle);
            return;
        }

        $userManager = $this->managers->getManagerOf('user');
        $user =  $userManager->findById($this->httpRequest->postData('id'));

        if ($user->getId() == $this->auth->getUser()->getId()) {
            $handle['errors'][] = 'Vous ne pouvez pas vous supprimer.';
            $this->httpResponse->ajaxResponse($handle);
            return;
        }

        $mailer = new MailService();
        if (!$mailer->sendUserDeleteEmail($user, $this->httpRequest->postData('message_field'))) {
            $handle['errors'][] = 'Erreur lors de l\'envoi du mail.';
            $this->httpResponse->ajaxResponse($handle);
            return;
        }

        $deleter = new FilesService();
        if (!$deleter->deleteDirectory('uploads/blog/' . $user->getId())) {
            $handle['errors'][] = "Erreur lors de la suppression des images";
            $this->httpResponse->ajaxResponse($handle);
            return;
        }
        if ($userManager->delete($user->getId())) {
            $handle['success'] = true;
            $this->flash->addMessage('L\'utilisateur a bien été supprimé.', Flash::SUCCESS);
            $this->httpResponse->ajaxResponse($handle);
            return;
        }
        $handle['errors'][] = 'Error lors de la suppression.';
        $this->httpResponse->ajaxResponse($handle);
    }

    /**
     * Moderate comment
     */
    public function moderateCommentAction()
    {
        $handle = [
            'success' => false,
            'errors' => [],
        ];

        if (!$this->isCsrfTokenValid($this->httpRequest->postData('token'), false)) {
            $handle['errors'][] = 'Une erreur s\'est produite. Merci d\'actualisez la page et de recommencer.';
            $this->httpResponse->ajaxResponse($handle);
            return;
        }
        $commentManager = $this->managers->getManagerOf('Comment');
        $comment = $commentManager->getUnique($this->httpRequest->postData('id'));

        $this->requiredLogin('user');


        if ($this->httpRequest->postData('action') === "valider") {
            if ($this->auth->getUser()->isGranted('admin')) {
                $comment->setValidated(true);
                if ($commentManager->save($comment)) {
                    $handle['success'] = true;
                    $handle['comment'] = $comment->getId();
                    $this->httpResponse->ajaxResponse($handle);
                    return;
                }

            }
        }

        if ($this->httpRequest->postData('action') === "supprimer") {
            if ($this->auth->getUser()->isGranted('admin') || $this->auth->getUser()->getId() == $comment->getUser()->getId()) {
                if ($commentManager->delete($comment->getId())) {
                    $handle['success'] = true;
                    $handle['comment'] = $comment->getId();
                    $this->httpResponse->ajaxResponse($handle);
                    return;
                }

            }
        }

        $handle['errors'][] = 'Une erreur s\'est produite (1).';
        $this->httpResponse->ajaxResponse($handle);
    }

    private function entityDeleter($toDelete)
    {
        $result = 'success';
        $classes = [
            'User' => 'Blog\Entities\User',
            'BlogPost' => 'Blog\Entities\BlogPost'
        ];
        $type = get_class($toDelete);
        if ($type !== $classes['User'] && $type !== $classes['BlogPost']) {
            return 'Error lors de la suppression.';
        }


        $postManager = $this->managers->getManagerOf('BlogPost');
        $deleter = new FilesService();

        if ($type === $classes['User']) {
            $dir = "uploads/blog/" . $toDelete->getId();
            if (!$deleter->deleteDirectory($dir)) {
                return 'Error lors de la suppression des images.';
            }
            if (!$postManager->deleteByUser($toDelete->getId())) {
                return 'Error lors de la suppression des posts.';
            }
        }
        if ($type === $classes['BlogPost']) {
            $dir = "uploads/blog/" . $toDelete->getUser()->getId() . '/' . $toDelete->getId();
            if (!$deleter->deleteDirectory($dir)) {
                return 'Error lors de la suppression des images.';
            }
            if (!$postManager->delete($toDelete->getId())) {
                return 'Error lors de la suppression du post.';
            }
        }
        return $result;

    }

    /**
     * Load more unvalidated comments
     */
    public function loadUnvalidatedCommentsAction() {
        $comments['end'] = false;

        $commentManager = $this->managers->getManagerOf('Comment');
        $comments['comments'] = $commentManager->getUnvalidated($this->httpRequest->postData('offset'));

        if (count($comments['comments']) < $this->config->get('pagination')) {
            $comments['end'] = true;
        }
        $this->httpResponse->ajaxResponse($comments);
    }

    /**
     * Load more post comments
     */
    public function loadPostCommentsAction() {
        $comments['end'] = false;

        $commentManager = $this->managers->getManagerOf('Comment');
        $comments['comments'] = $commentManager->getByPost($this->auth->getUser(), $this->httpRequest->postData('post_id'), $this->httpRequest->postData('offset'));

        if (count($comments['comments']) < $this->config->get('pagination')) {
            $comments['end'] = true;
        }
        $this->httpResponse->ajaxResponse($comments);
    }

    /**
     * Load more posts
     */
    public function loadPostsAction() {
        $posts['end'] = false;

        $postManager = $this->managers->getManagerOf('BlogPost');
        $posts['posts'] = $postManager->getList($this->httpRequest->postData('offset'));

        if (count($posts['posts']) < $this->config->get('pagination')) {
            $posts['end'] = true;
        }
        $this->httpResponse->ajaxResponse($posts);
    }
}