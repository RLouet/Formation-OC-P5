<?php


namespace Blog\Entities;


use Core\Entity;
use JsonSerializable;

class Skill extends Entity implements JsonSerializable
{
    protected int $blogId;
    protected string $value;

    const INVALID_BLOG_ID = 1;
    const INVALID_VALUE = 2;

    public function  isValid()
    {
        return !(empty($this->blogId) || empty($this->value));
    }

    public function jsonSerialize()
    {
        return [
            'id' => $this->getId(),
            'value' => $this->getValue(),
            'errors' => $this->getErrors(),
            'blogId' => $this->getBlogId(),
        ];
    }


    // SETTERS //

    public function setBlogId(int $blogId): Skill
    {
        if (empty($blogId)) {
            $this->errors[] = self::INVALID_BLOG_ID;
            return $this;
        }
        $this->blogId = $blogId;
        return $this;
    }

    public function setValue(string $value): Skill
    {
        if (empty($value) || !preg_match('/^[\da-zÀ-ÖØ-öø-ÿœŒ][\d\'a-zÀ-ÖØ-öø-ÿœŒ -]{0,48}[\da-zÀ-ÖØ-öø-ÿœŒ]$/i', $value)) {
            $this->errors[] = self::INVALID_VALUE;
            return $this;
        }
        $this->value = $value;
        return $this;
    }


    // GETTERS //

    public function getBlogId(): int
    {
        return $this->blogId;
    }

    public function getValue(): string
    {
        return $this->value;
    }
}