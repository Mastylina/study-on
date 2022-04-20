<?php

namespace App\Entity;

use App\Repository\CourseRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
/**
 * @ORM\Entity
 * @UniqueEntity(
 *     fields={"character_code"},
 *     errorPath="character_code",
 *     message="This is already a Course with this character_code."
 * )
 */
/**
 * @ORM\Entity(repositoryClass=CourseRepository::class)
 */
class Course
{
    /**
     * @ORM\Id
     * @ORM\GeneratedValue
     * @ORM\Column(type="integer")
     */
    private $id;

    /**
     * @ORM\Column(type="string", length=255, unique=true)
     */
    private $character_code;

    /**
     * @ORM\Column(type="string", length=255)
     */
    private $name_course;

    /**
     * @ORM\Column(type="string", length=1000)
     */
    private $description;

    /**
     * @ORM\OneToMany(targetEntity=Lesson::class, mappedBy="cours_lesson",cascade={"persist"})
     */
    private $lessons;

    public function __construct()
    {
        $this->lessons = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCharactercode(): ?string
    {
        return $this->character_code;
    }

    public function setCharactercode(string $character_code): self
    {
        $this->character_code = $character_code;

        return $this;
    }

    public function getnameCourse(): ?string
    {
        return $this->name_course;
    }

    public function setnameCourse(string $name_course): self
    {
        $this->name_course = $name_course;

        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(string $description): self
    {
        $this->description = $description;

        return $this;
    }

    /**
     * @return Collection<int, Lesson>
     */
    public function getLessons(): Collection
    {
        return $this->lessons;
    }

    public function addLesson(Lesson $lesson): self
    {
        if (!$this->lessons->contains($lesson)) {
            $this->lessons[] = $lesson;
            $lesson->setCoursLesson($this);
        }

        return $this;
    }

    public function removeLesson(Lesson $lesson): self
    {
        if ($this->lessons->removeElement($lesson)) {
            // set the owning side to null (unless already changed)
            if ($lesson->getCoursLesson() === $this) {
                $lesson->setCoursLesson(null);
            }
        }

        return $this;
    }
}
