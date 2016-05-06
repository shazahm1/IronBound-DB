<?php
/**
 * Contains tests for the FluentQuery object.
 *
 * @author    Iron Bound Designs
 * @since     2.0
 * @license   MIT
 * @copyright Iron Bound Designs, 2016.
 */

namespace IronBound\DB\Tests;

use IronBound\DB\Manager;
use IronBound\DB\Model;
use IronBound\DB\Query\FluentQuery;
use IronBound\DB\Query\Tag\Order;
use IronBound\DB\Tests\Stub\Models\Author;
use IronBound\DB\Tests\Stub\Tables\Authors;
use IronBound\DB\Tests\Stub\Tables\Books;
use IronBound\WPEvents\EventDispatcher;

/**
 * Class Test_Fluent_Query
 * @package IronBound\DB\Tests
 */
class Test_Fluent_Query extends \WP_UnitTestCase {

	public function setUp() {
		parent::setUp();

		Manager::register( new Authors() );
		Manager::register( new Books() );
		Manager::maybe_install_table( Manager::get( 'authors' ) );
		Manager::maybe_install_table( Manager::get( 'books' ) );

		Model::set_event_dispatcher( new EventDispatcher() );
	}

	public function test_no_constraints() {

		$a1 = Author::create( array(
			'name'       => 'John Smith',
			'birth_date' => new \DateTime( '1945-02-01' )
		) );
		$a2 = Author::create( array(
			'name'       => 'Amy Goodman',
			'birth_date' => new \DateTime( '1957-11-14' )
		) );

		$results = FluentQuery::from_model( get_class( new Author() ) )->results();

		$this->assertEquals( 2, $results->count() );
		$this->assertTrue( $results->containsKey( $a1->get_pk() ) );
		$this->assertTrue( $results->containsKey( $a2->get_pk() ) );
	}

	public function test_where_like() {

		$a1 = Author::create( array(
			'name'       => 'John Smith',
			'birth_date' => new \DateTime( '1945-02-01' )
		) );
		$a2 = Author::create( array(
			'name'       => 'Jane Smith',
			'birth_date' => new \DateTime( '1957-11-14' )
		) );

		$results = FluentQuery::from_model( get_class( new Author() ) )->where( 'name', 'LIKE', 'John%' )->results();

		$this->assertEquals( 1, $results->count() );
		$this->assertTrue( $results->containsKey( $a1->get_pk() ) );
	}

	public function test_nested_where() {

		$a1 = Author::create( array(
			'name'       => 'John Smith',
			'birth_date' => new \DateTime( '1945-02-01' )
		) );
		$a2 = Author::create( array(
			'name'       => 'Jane Smith',
			'birth_date' => new \DateTime( '1957-11-14' ),
			'bio'        => "I'm the best!"
		) );

		$results = FluentQuery::from_model( get_class( new Author() ) )
		                      ->where( 'name', 'LIKE', 'John%', function ( FluentQuery $query ) {
			                      $query->or_where( 'bio', true, "I'm the best!" );
		                      } )->results();

		$this->assertEquals( 2, $results->count() );
		$this->assertTrue( $results->containsKey( $a1->get_pk() ) );
		$this->assertTrue( $results->containsKey( $a2->get_pk() ) );
	}

	public function test_take_1() {

		$a1 = Author::create( array(
			'name'       => 'John Smith',
			'birth_date' => new \DateTime( '1945-02-01' )
		) );
		$a2 = Author::create( array(
			'name'       => 'Jane Smith',
			'birth_date' => new \DateTime( '1957-11-14' ),
			'bio'        => "I'm the best!"
		) );

		$results = FluentQuery::from_model( get_class( new Author() ) )->order_by( 'birth_date', Order::DESC )->take( 1 )->results();

		$this->assertEquals( 1, $results->count() );
		$this->assertTrue( $results->containsKey( $a2->get_pk() ) );
	}

	public function test_chunk() {

		$a1 = Author::create( array(
			'name'       => 'John Smith',
			'birth_date' => new \DateTime( '1945-02-01' )
		) );
		$a2 = Author::create( array(
			'name'       => 'James Smith',
			'birth_date' => new \DateTime( '1945-02-01' )
		) );
		$a5 = Author::create( array(
			'name'       => 'Tom Riddle',
			'birth_date' => new \DateTime( '1945-02-01' )
		) );
		$a6 = Author::create( array(
			'name'       => 'Voldemort',
			'birth_date' => new \DateTime( '1945-02-01' )
		) );
		$a3 = Author::create( array(
			'name'       => 'Amy Smith',
			'birth_date' => new \DateTime( '1945-02-01' )
		) );
		$a4 = Author::create( array(
			'name'       => 'Jack Smith',
			'birth_date' => new \DateTime( '1945-02-01' )
		) );

		$touched = array();

		FluentQuery::from_model( get_class( new Author() ) )->where( 'name', 'LIKE', '%Smith' )->each( 2, function ( Author $author ) use ( &$touched ) {

			$touched[] = $author->get_pk();
		} );

		$this->assertEqualSets( array( $a1->get_pk(), $a2->get_pk(), $a3->get_pk(), $a4->get_pk() ), $touched );
	}
}