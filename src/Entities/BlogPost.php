<?php


namespace Blog\Entities;


use Core\Entity;
use Core\ObjectCollection;
use JsonSerializable;
use DateTime;

class BlogPost extends Entity implements JsonSerializable
{
    protected int $userId;
    protected User $user;

    protected string $title = "";
    protected string $chapo = "";
    protected string $content = "";

    protected ObjectCollection $images;

    protected DateTime $editDate;
    protected ?PostImage $hero = null;

    const INVALID_USER_ID = 1;
    const INVALID_TITLE = 2;
    const INVALID_CHAPO = 3;
    const INVALID_CONTENT = 4;
    const INVALID_EDIT_DATE = 5;
    const INVALID_HERO = 6;

    public function jsonSerialize()
    {
        return [
            'id' => $this->getId(),
            'date' => $this->getEditDate()->format('d/m/Y à H:i:s'),
            'title' => $this->getTitle(),
            'heroUrl' => $this->getHero()?$this->getHero()->getUrl():null,
            'heroName' => $this->getHero()?$this->getHero()->getName():null,
            'chapo' => nl2br($this->getChapo()),
            'username' => $this->getUser()->getUsername(),
            'userId' => $this->getUser()->getId(),
            'userBanished' => $this->getUser()->getBanished(),
            'userAdmin' => $this->getUser()->isGranted('admin')
        ];
    }

    public function __construct(array $data = [])
    {
        $this->images = new ObjectCollection();
        $this->editDate = new DateTime();
        parent::__construct($data);
    }

    public function isValid()
    {
        return !(empty($this->userId) || empty($this->title) || empty($this->chapo) || empty($this->content) || empty($this->editDate) || !empty($this->errors));
    }

    //  Setters  //

    public function setUserId(int $userId): BlogPost
    {
        if (empty($userId)) {
            $this->errors[] = self::INVALID_USER_ID;
            return $this;
        }
        $this->userId = $userId;
        return $this;
    }

    public function setUser(User $user): BlogPost
    {
        $this->user = $user;
        $this->userId = $user->getId();
        return $this;
    }

    public function setTitle(string $title): BlogPost
    {
        $this->title = $title;
        if (empty($title) || !preg_match('/^.{2,128}$/i', $title)) {
            $this->errors[] = self::INVALID_TITLE;
            return $this;
        }
        return $this;
    }

    public function setChapo(string $chapo): BlogPost
    {
        $this->chapo = $chapo;
        if (empty($chapo) || !preg_match('/^.{5,255}$/im', $chapo)) {
            $this->errors[] = self::INVALID_CHAPO;
            return $this;
        }
        return $this;
    }

    public function setContent(string $content): BlogPost
    {
        $this->content = $content;
        if (empty($content) || !preg_match('/^.{5,}$/im', $content)) {
            $this->errors[] = self::INVALID_CONTENT;
            return $this;
        }
        return $this;
    }

    public function setEditDate(DateTime $editDate): BlogPost
    {
        if (empty($editDate)) {
            $this->errors[] = self::INVALID_EDIT_DATE;
            return $this;
        }
        $this->editDate = $editDate;
        return $this;
    }

    public function setHero(PostImage $hero): BlogPost
    {
        if (!$this->images->contains($hero) && $hero->getBlogPostId() === $this->getId()) {
            $this->images->attach($hero);
        }
        if (empty($hero) || !$this->getImages()->getById($hero->getId())) {
            $this->errors[] = self::INVALID_HERO;
            return $this;
        }
        $this->hero = $hero;
        return $this;
    }

    public function addImage(PostImage $image)
    {
        if (!$this->images->contains($image)) {
            $this->images->attach($image);
        }
    }
    public function removeImage(PostImage $image)
    {
        if ($this->getHero() === $image) {
            $this->hero = null;
        }
        if ($this->images->contains($image)) {
            $this->images->detach($image);
        }
    }


    //  -- Getters --  //

    public function getUserId(): int
    {
        return $this->userId;
    }

    public function getUser(): User
    {
        return $this->user;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function getChapo(): string
    {
        return $this->chapo;
    }

    public function getContent(): string
    {
        return $this->content;
    }

    public function getEditDate(): DateTime
    {
        if ($this->editDate instanceof DateTime) {
            return $this->editDate;
        }

        return new DateTime($this->editDate);
    }

    public function getHero(): ?PostImage
    {
        return $this->hero;
    }

    public function getImages(): ObjectCollection
    {
        return $this->images;
    }
}