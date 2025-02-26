<?php

declare(strict_types=1);

namespace Sabre\CardDAV\Backend;

use PHPUnit\Framework\TestCase;
use Sabre\CardDAV;
use Sabre\DAV\DbTestHelperTrait;
use Sabre\DAV\PropPatch;

abstract class AbstractPDOTestCase extends TestCase
{
    use DbTestHelperTrait;

    /**
     * @var CardDAV\Backend\PDO
     */
    protected $backend;

    public function setup(): void
    {
        $this->dropTables([
            'addressbooks',
            'cards',
            'addressbookchanges',
        ]);
        $this->createSchema('addressbooks');
        $pdo = $this->getPDO();

        $this->backend = new PDO($pdo);
        $pdo->exec("INSERT INTO addressbooks (principaluri, displayname, uri, description, synctoken) VALUES ('principals/user1', 'book1', 'book1', 'addressbook 1', 1)");
        $pdo->exec("INSERT INTO cards (addressbookid, carddata, uri, lastmodified, etag, size) VALUES (1, 'card1', 'card1', 0, '".md5('card1')."', 5)");
    }

    public function testGetAddressBooksForUser()
    {
        $result = $this->backend->getAddressBooksForUser('principals/user1');

        $expected = [
            [
                'id' => 1,
                'uri' => 'book1',
                'principaluri' => 'principals/user1',
                '{DAV:}displayname' => 'book1',
                '{'.CardDAV\Plugin::NS_CARDDAV.'}addressbook-description' => 'addressbook 1',
                '{http://calendarserver.org/ns/}getctag' => 1,
                '{http://sabredav.org/ns}sync-token' => 1,
            ],
        ];

        self::assertEquals($expected, $result);
    }

    public function testUpdateAddressBookInvalidProp()
    {
        $propPatch = new PropPatch([
            '{DAV:}displayname' => 'updated',
            '{'.CardDAV\Plugin::NS_CARDDAV.'}addressbook-description' => 'updated',
            '{DAV:}foo' => 'bar',
        ]);

        $this->backend->updateAddressBook(1, $propPatch);
        $result = $propPatch->commit();

        self::assertFalse($result);

        $result = $this->backend->getAddressBooksForUser('principals/user1');

        $expected = [
            [
                'id' => 1,
                'uri' => 'book1',
                'principaluri' => 'principals/user1',
                '{DAV:}displayname' => 'book1',
                '{'.CardDAV\Plugin::NS_CARDDAV.'}addressbook-description' => 'addressbook 1',
                '{http://calendarserver.org/ns/}getctag' => 1,
                '{http://sabredav.org/ns}sync-token' => 1,
            ],
        ];

        self::assertEquals($expected, $result);
    }

    public function testUpdateAddressBookNoProps()
    {
        $propPatch = new PropPatch([
        ]);

        $this->backend->updateAddressBook(1, $propPatch);
        $result = $propPatch->commit();
        self::assertTrue($result);

        $result = $this->backend->getAddressBooksForUser('principals/user1');

        $expected = [
            [
                'id' => 1,
                'uri' => 'book1',
                'principaluri' => 'principals/user1',
                '{DAV:}displayname' => 'book1',
                '{'.CardDAV\Plugin::NS_CARDDAV.'}addressbook-description' => 'addressbook 1',
                '{http://calendarserver.org/ns/}getctag' => 1,
                '{http://sabredav.org/ns}sync-token' => 1,
            ],
        ];

        self::assertEquals($expected, $result);
    }

    public function testUpdateAddressBookSuccess()
    {
        $propPatch = new PropPatch([
            '{DAV:}displayname' => 'updated',
            '{'.CardDAV\Plugin::NS_CARDDAV.'}addressbook-description' => 'updated',
        ]);

        $this->backend->updateAddressBook(1, $propPatch);
        $result = $propPatch->commit();

        self::assertTrue($result);

        $result = $this->backend->getAddressBooksForUser('principals/user1');

        $expected = [
            [
                'id' => 1,
                'uri' => 'book1',
                'principaluri' => 'principals/user1',
                '{DAV:}displayname' => 'updated',
                '{'.CardDAV\Plugin::NS_CARDDAV.'}addressbook-description' => 'updated',
                '{http://calendarserver.org/ns/}getctag' => 2,
                '{http://sabredav.org/ns}sync-token' => 2,
            ],
        ];

        self::assertEquals($expected, $result);
    }

    public function testDeleteAddressBook()
    {
        $this->backend->deleteAddressBook(1);

        self::assertEquals([], $this->backend->getAddressBooksForUser('principals/user1'));
    }

    public function testCreateAddressBookUnsupportedProp()
    {
        $this->expectException(\Sabre\DAV\Exception\BadRequest::class);
        $this->backend->createAddressBook('principals/user1', 'book2', [
            '{DAV:}foo' => 'bar',
        ]);
    }

    public function testCreateAddressBookSuccess()
    {
        $this->backend->createAddressBook('principals/user1', 'book2', [
            '{DAV:}displayname' => 'book2',
            '{'.CardDAV\Plugin::NS_CARDDAV.'}addressbook-description' => 'addressbook 2',
        ]);

        $expected = [
            [
                'id' => 1,
                'uri' => 'book1',
                'principaluri' => 'principals/user1',
                '{DAV:}displayname' => 'book1',
                '{'.CardDAV\Plugin::NS_CARDDAV.'}addressbook-description' => 'addressbook 1',
                '{http://calendarserver.org/ns/}getctag' => 1,
                '{http://sabredav.org/ns}sync-token' => 1,
            ],
            [
                'id' => 2,
                'uri' => 'book2',
                'principaluri' => 'principals/user1',
                '{DAV:}displayname' => 'book2',
                '{'.CardDAV\Plugin::NS_CARDDAV.'}addressbook-description' => 'addressbook 2',
                '{http://calendarserver.org/ns/}getctag' => 1,
                '{http://sabredav.org/ns}sync-token' => 1,
            ],
        ];
        $result = $this->backend->getAddressBooksForUser('principals/user1');
        self::assertEquals($expected, $result);
    }

    public function testGetCards()
    {
        $result = $this->backend->getCards(1);

        $expected = [
            [
                'id' => 1,
                'uri' => 'card1',
                'lastmodified' => 0,
                'etag' => '"'.md5('card1').'"',
                'size' => 5,
            ],
        ];

        self::assertEquals($expected, $result);
    }

    public function testGetCard()
    {
        $result = $this->backend->getCard(1, 'card1');

        $expected = [
            'id' => 1,
            'uri' => 'card1',
            'carddata' => 'card1',
            'lastmodified' => 0,
            'etag' => '"'.md5('card1').'"',
            'size' => 5,
        ];

        if (is_resource($result['carddata'])) {
            $result['carddata'] = stream_get_contents($result['carddata']);
        }

        self::assertEquals($expected, $result);
    }

    /**
     * @depends testGetCard
     */
    public function testCreateCard()
    {
        $result = $this->backend->createCard(1, 'card2', 'data2');
        self::assertEquals('"'.md5('data2').'"', $result);
        $result = $this->backend->getCard(1, 'card2');
        self::assertEquals(2, $result['id']);
        self::assertEquals('card2', $result['uri']);
        if (is_resource($result['carddata'])) {
            $result['carddata'] = stream_get_contents($result['carddata']);
        }
        self::assertEquals('data2', $result['carddata']);
    }

    /**
     * @depends testCreateCard
     */
    public function testGetMultiple()
    {
        $result = $this->backend->createCard(1, 'card2', 'data2');
        $result = $this->backend->createCard(1, 'card3', 'data3');
        $check = [
            [
                'id' => 1,
                'uri' => 'card1',
                'carddata' => 'card1',
                'lastmodified' => 0,
            ],
            [
                'id' => 2,
                'uri' => 'card2',
                'carddata' => 'data2',
                'lastmodified' => time(),
            ],
            [
                'id' => 3,
                'uri' => 'card3',
                'carddata' => 'data3',
                'lastmodified' => time(),
            ],
        ];

        $result = $this->backend->getMultipleCards(1, ['card1', 'card2', 'card3']);

        foreach ($check as $index => $node) {
            foreach ($node as $k => $v) {
                $expected = $v;
                $actual = $result[$index][$k];

                switch ($k) {
                    case 'lastmodified':
                        self::assertIsInt($actual);
                        break;
                    case 'carddata':
                        if (is_resource($actual)) {
                            $actual = stream_get_contents($actual);
                        }
                        // no break intended.
                    default:
                        self::assertEquals($expected, $actual);
                        break;
                }
            }
        }
    }

    /**
     * @depends testGetCard
     */
    public function testUpdateCard()
    {
        $result = $this->backend->updateCard(1, 'card1', 'newdata');
        self::assertEquals('"'.md5('newdata').'"', $result);

        $result = $this->backend->getCard(1, 'card1');
        self::assertEquals(1, $result['id']);
        if (is_resource($result['carddata'])) {
            $result['carddata'] = stream_get_contents($result['carddata']);
        }
        self::assertEquals('newdata', $result['carddata']);
    }

    /**
     * @depends testGetCard
     */
    public function testDeleteCard()
    {
        $this->backend->deleteCard(1, 'card1');
        $result = $this->backend->getCard(1, 'card1');
        self::assertFalse($result);
    }

    public function testGetChanges()
    {
        $backend = $this->backend;
        $id = $backend->createAddressBook(
            'principals/user1',
            'bla',
            []
        );
        $result = $backend->getChangesForAddressBook($id, null, 1);

        self::assertEquals([
            'syncToken' => 1,
            'added' => [],
            'modified' => [],
            'deleted' => [],
        ], $result);

        $currentToken = $result['syncToken'];

        $dummyCard = "BEGIN:VCARD\r\nEND:VCARD\r\n";

        $backend->createCard($id, 'card1.ics', $dummyCard);
        $backend->createCard($id, 'card2.ics', $dummyCard);
        $backend->createCard($id, 'card3.ics', $dummyCard);
        $backend->updateCard($id, 'card1.ics', $dummyCard);
        $backend->deleteCard($id, 'card2.ics');

        $result = $backend->getChangesForAddressBook($id, $currentToken, 1);

        self::assertEquals([
            'syncToken' => 6,
            'modified' => ['card1.ics'],
            'deleted' => ['card2.ics'],
            'added' => ['card3.ics'],
        ], $result);
    }
}
