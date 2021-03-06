<?php

/**
 * @testCase
 * @dataProvider ../../../sections.ini
 */

namespace NextrasTests\Orm\Integration\Repository;

use Mockery;
use Nextras\Orm\InvalidStateException;
use NextrasTests\Orm\Book;
use NextrasTests\Orm\DataTestCase;
use Tester\Assert;

$dic = require_once __DIR__ . '/../../../bootstrap.php';


class RepostiroyCascadeRemoveTest extends DataTestCase
{
	public function testBasicCascadeRemove()
	{
		$author = $this->orm->authors->getById(2);

		$bookDiff = new Book();
		$bookDiff->author = $this->orm->authors->getById(1);
		$bookDiff->translator = $author;
		$bookDiff->publisher = 1;
		$bookDiff->title = 'Book 5';

		$this->orm->books->persistAndFlush($bookDiff);

		$bookSame = $this->orm->books->getById(3);

		$this->orm->authors->removeAndFlush($author, TRUE);

		Assert::true($bookDiff->isPersisted());
		Assert::null($bookDiff->translator);
		Assert::notEqual(NULL, $bookDiff->author);

		Assert::false($bookSame->isPersisted());
	}


	public function testForeignKeyConstraintRemove()
	{
		Assert::throws(function () {
			$this->orm->publishers->removeAndFlush(1);
		}, InvalidStateException::class, 'Cannot remove NextrasTests\Orm\Publisher::$id=1 because NextrasTests\Orm\Book::$publisher cannot be a null.');
	}
}


$test = new RepostiroyCascadeRemoveTest($dic);
$test->run();
