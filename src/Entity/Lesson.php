<?php

namespace App\Entity;

use App\Repository\LessonRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass=LessonRepository::class)
 */
class Lesson
{
    /**
     * @ORM\Id
     * @ORM\GeneratedValue
     * @ORM\Column(type="integer")
     */
    private $id;

    /**
     * @ORM\Column(type="string", length=255)
     */
    private $name_lesson;

    /**
     * @ORM\Column(type="text")
     */
    private $content_lesson;

    /**
     * @ORM\Column(type="integer")
     */
    private $number_lesson;

    /**
     * @ORM\ManyToOne(targetEntity=Course::class, inversedBy="lessons",cascade={"persist"})
     * @ORM\JoinColumn(nullable=false)
     */
    private $cours_lesson;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getNameLesson(): ?string
    {
        return $this->name_lesson;
    }

    public function setNameLesson(string $name_lesson): self
    {
        $this->name_lesson = $name_lesson;

        return $this;
    }

    public function getContentLesson(): ?string
    {
        return $this->content_lesson;
    }

    public function setContentLesson(string $content_lesson): self
    {
        $this->content_lesson = $content_lesson;

        return $this;
    }

    public function getnumberLesson(): ?int
    {
        return $this->number_lesson;
    }

    public function setnumberLesson(int $number_lesson): self
    {
        $this->number_lesson = $number_lesson;

        return $this;
    }

    public function getCoursLesson(): ?Course
    {
        return $this->cours_lesson;
    }

    public function setCoursLesson(?Course $cours_lesson): self
    {
        $this->cours_lesson = $cours_lesson;

        return $this;
    }
}
