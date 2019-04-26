<?php

declare(strict_types=1);

namespace Doctrine\Tests\ORM\Functional\Ticket;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\Annotation as ORM;
use Doctrine\ORM\Event\LifecycleEventArgs;
use Doctrine\ORM\Events;
use Doctrine\Tests\OrmFunctionalTestCase;
use function in_array;
use function json_decode;

/**
 * @group bug
 */
class MyBugTest extends OrmFunctionalTestCase
{
    protected function setUp() : void
    {
        parent::setUp();

        $this->schemaTool->createSchema(
            [
                $this->em->getClassMetadata(MyBugUser::class),
                $this->em->getClassMetadata(MyBugChild::class),
            ]
        );

        $this->loadFixture();
    }

    protected function tearDown() : void
    {
        parent::tearDown();

        $this->schemaTool->dropSchema(
            [
                $this->em->getClassMetadata(MyBugUser::class),
                $this->em->getClassMetadata(MyBugChild::class),
            ]
        );
    }

    public function testPostLoadListenerShouldBeLoadedWhenCascadeRefresh() : void
    {
        $listener = new MyBugPostLoadListener();
        $eventManager = $this->em->getEventManager();
        $eventManager->addEventListener([Events::postLoad], $listener);

        /** @var MyBugUser[] $result */
        $result = $this->em->getRepository(MyBugUser::class)->findAll();

        self::assertCount(1, $result);
        self::assertCount(2, $result[0]->children);

        self::assertSame(2, $listener->getChildCallsCount());

        $this->em->refresh($result[0]);

        self::assertSame(4, $listener->getChildCallsCount());
    }

    private function loadFixture() : void
    {
        $user     = new MyBugUser();
        $bugChild1 = new MyBugChild();
        $bugChild2 = new MyBugChild();

        $bugChild1->user = $user;
        $bugChild2->user = $user;

        $user->name      = 'Gblanco';
        $user->children[] = $bugChild1;
        $user->children[] = $bugChild2;

        $this->em->persist($user);

        $this->em->flush();
        $this->em->clear();
    }
}


class MyBugPostLoadListener
{
    private $childCallsCount = 0;

    public function postLoad(LifecycleEventArgs $event) : void
    {
        $entity = $event->getEntity();

        if (! ($entity instanceof MyBugChild)) {
            return;
        }

        $this->childCallsCount++;
    }

    /**
     * @return int
     */
    public function getChildCallsCount(): int
    {
        return $this->childCallsCount;
    }
}


/**
 * @ORM\Entity
 */
class MyBugUser
{
    /**
     * @ORM\Id @ORM\GeneratedValue
     * @ORM\Column(type="integer")
     *
     * @var int
     */
    public $id;

    /**
     * @ORM\OneToMany(
     *     targetEntity=MyBugChild::class,
     *     mappedBy="user",
     *     cascade={"persist", "refresh"}
     * )
     *
     * @var MyBugChild
     */
    public $children;

    /**
     * MyBugUser constructor.
     */
    public function __construct()
    {
        $this->children = new ArrayCollection();
    }

}

/**
 * @ORM\Entity
 */
class MyBugChild
{
    /**
     * @ORM\Id @ORM\GeneratedValue
     * @ORM\Column(type="integer")
     *
     * @var int
     */
    public $id;

    /**
     * @ORM\ManyToOne(
     *     targetEntity=MyBugUser::class,
     *     inversedBy="biography"
     * )
     *
     * @var MyBugUser
     */
    public $user;

    /**
     * @ORM\Column(type="text", nullable=true)
     *
     * @var string
     */
    public $content;
}
