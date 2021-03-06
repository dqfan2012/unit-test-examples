<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Status\Providers;

use PDO;
use PDOException;
use PDOStatement;
use PHPUnit\Framework\TestCase;
use App\Services\Status\Providers\PdoProvider;
use App\Contracts\Services\Status\StatusServiceProvider;

/**
 * PdoProvider tests.
 *
 * It's easy to imagine testing a class that uses a database connection (in this case an
 * {@link \PDO} connection) would actually connect to a database. But by passing in the
 * {@link \PDO} connection object to the class, we can mock that object in our tests below
 * so we never have to leave the application boundary to test this class. As long as we
 * force our mock to behave as a normal {@link \PDO} class would (think adhering to the
 * documentation for the {@link \PDO} class), then we can be sure our class behaves in accordance
 * given our assumption of how the {@link \PDO} class behaves.
 *
 * @author    Brandon Clothier <brandon14125@gmail.com>
 *
 * @version   1.0.0
 *
 * @license   MIT
 * @copyright 2018
 */
class PdoProviderTest extends TestCase
{
    public function testGetStatusHandlesPDOExceptions(): void
    {
        $db = $this->createMock(PDO::class);

        $db->expects($this::once())->method('query')->will(
            $this::throwException(new PDOException('This is a test PDO exception'))
        );

        $instance = new PdoProvider($db);

        $status = $instance->getStatus();

        $this::assertEquals(['status' => StatusServiceProvider::STATUS_ERROR], $status);
    }

    public function testGetStatusHandlesStatementFailure(): void
    {
        $db = $this->createMock(PDO::class);

        // We expect query to be called, and we mock it returning false to simulate a failure to create the
        // PDOStatement object.
        $db->expects($this::once())->method('query')->willReturn(false);

        $instance = new PdoProvider($db);

        $status = $instance->getStatus();

        $this::assertEquals(['status' => StatusServiceProvider::STATUS_ERROR], $status);
    }

    public function testGetStatusHandlesStatementExecFailure(): void
    {
        $db = $this->createMock(PDO::class);
        $statement = $this->createMock(PDOStatement::class);

        // We expect that the execute function will be called on the statement mock and
        // we mock it to return false to simulate the statement execution failure.
        $statement->expects($this::once())->method('execute')->willReturn(false);

        // We expect query to be called, and we mock it returning the mocked statement.
        $db->expects($this::once())->method('query')->willReturn($statement);

        $instance = new PdoProvider($db);

        $status = $instance->getStatus();

        $this::assertEquals(['status' => StatusServiceProvider::STATUS_ERROR], $status);
    }

    public function testGetStatusHandlesStatementExecPDOException(): void
    {
        $db = $this->createMock(PDO::class);
        $statement = $this->createMock(PDOStatement::class);

        // We expect that the execute function will be called on the statement mock and
        // we mock it to throw an exception to simulate a query failure when PDO is set
        // to throw exceptions.
        $statement->expects($this::once())->method('execute')->will(
            $this::throwException(new PDOException('This is a test!'))
        );

        // We expect query to be called, and we mock it returning the mocked statement.
        $db->expects($this::once())->method('query')->willReturn($statement);

        $instance = new PdoProvider($db);

        $status = $instance->getStatus();

        $this::assertEquals(['status' => StatusServiceProvider::STATUS_ERROR], $status);
    }

    public function testGetStatusHandlesStatementExecQueryStateError(): void
    {
        $db = $this->createMock(PDO::class);
        $statement = $this->createMock(PDOStatement::class);

        // We expect that the execute function will be called on the statement mock and
        // we mock it to return true.
        $statement->expects($this::once())->method('execute')->willReturn(true);
        // Simulate a call to errorCode returning something other than 00000 which is the SQL state
        // for success.
        $statement->expects($this::once())->method('errorCode')->willReturn('01002');

        // We expect query to be called, and we mock it returning the mocked statement.
        $db->expects($this::once())->method('query')->willReturn($statement);

        $instance = new PdoProvider($db);

        $status = $instance->getStatus();

        $this::assertEquals(['status' => StatusServiceProvider::STATUS_ERROR], $status);
    }

    public function testGetStatusHandlesFetchPDOException(): void
    {
        $db = $this->createMock(PDO::class);
        $statement = $this->createMock(PDOStatement::class);

        // We expect that the execute function will be called on the statement mock and
        // we mock it to return true.
        $statement->expects($this::once())->method('execute')->willReturn(true);
        // Mock a success error code.
        $statement->expects($this::once())->method('errorCode')->willReturn('00000');
        // Mock that fetching the results throws a PDOException
        $statement->expects($this::once())->method('fetch')->will(
            $this::throwException(new PDOException('This is a test!'))
        );

        // We expect query to be called, and we mock it returning the mocked statement.
        $db->expects($this::once())->method('query')->willReturn($statement);

        $instance = new PdoProvider($db);

        $status = $instance->getStatus();

        $this::assertEquals(['status' => StatusServiceProvider::STATUS_ERROR], $status);
    }

    public function testGetStatusHandlesFetchEmptyQueryResult(): void
    {
        $db = $this->createMock(PDO::class);
        $statement = $this->createMock(PDOStatement::class);

        // We expect that the execute function will be called on the statement mock and
        // we mock it to return true.
        $statement->expects($this::once())->method('execute')->willReturn(true);
        // Mock a success error code.
        $statement->expects($this::once())->method('errorCode')->willReturn('00000');
        // Mock the query returning no results. I mean this should never happen right? SELECT 1+1 never returning
        // 2? Oh well sue me.
        $statement->expects($this::once())->method('fetch')->willReturn([]);

        // We expect query to be called, and we mock it returning the mocked statement.
        $db->expects($this::once())->method('query')->willReturn($statement);

        $instance = new PdoProvider($db);

        $status = $instance->getStatus();

        $this::assertEquals(['status' => StatusServiceProvider::STATUS_ERROR], $status);
    }

    public function testGetStatusReturnsDatabaseStatus(): void
    {
        $db = $this->createMock(PDO::class);
        $statement = $this->createMock(PDOStatement::class);

        // We expect that the execute function will be called on the statement mock and
        // we mock it to return true.
        $statement->expects($this::once())->method('execute')->willReturn(true);
        // Mock a success error code.
        $statement->expects($this::once())->method('errorCode')->willReturn('00000');
        // Mock the query returning the correct results meaning we could hit the database with this
        // connection.
        $statement->expects($this::once())->method('fetch')->willReturn([0 => 2]);

        // We expect query to be called, and we mock it returning the mocked statement.
        $db->expects($this::once())->method('query')->willReturn($statement);

        $instance = new PdoProvider($db);

        $status = $instance->getStatus();

        $this::assertEquals(['status' => StatusServiceProvider::STATUS_OK], $status);
    }
}
