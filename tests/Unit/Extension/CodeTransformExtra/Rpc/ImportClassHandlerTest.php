<?php

namespace Phpactor\Tests\Unit\Extension\CodeTransformExtra\Rpc;

use Phpactor\CodeTransform\Domain\Refactor\ImportClass\NameImport;
use Phpactor\Extension\Rpc\Response\CollectionResponse;
use Phpactor\Tests\Unit\Extension\Rpc\HandlerTestCase;
use Phpactor\Extension\Rpc\Handler;
use Phpactor\CodeTransform\Domain\Refactor\ImportName;
use Phpactor\Extension\SourceCodeFilesystemExtra\SourceCodeFilestem\Application\ClassSearch;
use Phpactor\Extension\CodeTransformExtra\Rpc\ImportClassHandler;
use Phpactor\Extension\Rpc\Response\InputCallbackResponse;
use Phpactor\Extension\Rpc\Response\Input\ListInput;
use Phpactor\Extension\Rpc\Response\EchoResponse;
use Phpactor\CodeTransform\Domain\SourceCode;
use Phpactor\CodeTransform\Domain\Refactor\ImportClass\AliasAlreadyUsedException;
use Phpactor\Extension\Rpc\Response\Input\TextInput;
use Phpactor\CodeTransform\Domain\Refactor\ImportClass\NameAlreadyImportedException;
use Phpactor\TextDocument\ByteOffset;
use Phpactor\TextDocument\TextEdit;
use Phpactor\TextDocument\TextEdits;

class ImportClassHandlerTest extends HandlerTestCase
{
    const TEST_NAME = 'Foo';
    const TEST_OFFSET = 7;
    const TEST_PATH = '/path/to';
    const TEST_SOURCE = '<?php Foo';
    const TEST_ALIAS = 'Alias';

    /**
     * @var ObjectProphecy
     */
    private $importName;

    /**
     * @var ObjectProphecy
     */
    private $classSearch;

    public function setUp(): void
    {
        $this->importName = $this->prophesize(ImportName::class);
        $this->classSearch = $this->prophesize(ClassSearch::class);
    }

    protected function createHandler(): Handler
    {
        return new ImportClassHandler(
            $this->importName->reveal(),
            $this->classSearch->reveal(),
            'composer'
        );
    }

    public function testReturnsSuggestionsIfMultipleTargetsFound()
    {
        $this->classSearch->classSearch('composer', self::TEST_NAME)->willReturn([
            [
                'class' => 'Foobar',
            ],
            [
                'class' => 'Barfoo',
            ],
        ]);

        /** @var InputCallbackResponse $response */
        $response = $this->handle('import_class', [
            ImportClassHandler::PARAM_OFFSET => self::TEST_OFFSET,
            ImportClassHandler::PARAM_PATH => self::TEST_PATH,
            ImportClassHandler::PARAM_SOURCE => self::TEST_SOURCE
        ]);
        $this->assertInstanceOf(InputCallbackResponse::class, $response);
        $inputs = $response->inputs();
        $this->assertCount(1, $inputs);
        /** @var ListInput $input */
        $input = reset($inputs);
        $this->assertCount(2, $input->choices());
    }

    public function testShowsMessageIfNoClassesFound()
    {
        $this->classSearch->classSearch('composer', self::TEST_NAME)->willReturn([]);

        /** @var EchoResponse $response */
        $response = $this->handle('import_class', [
            ImportClassHandler::PARAM_OFFSET => self::TEST_OFFSET,
            ImportClassHandler::PARAM_PATH => self::TEST_PATH,
            ImportClassHandler::PARAM_SOURCE => self::TEST_SOURCE
        ]);
        $this->assertInstanceOf(EchoResponse::class, $response);
    }

    public function testImportsClassIfOnlyOneSuggestion()
    {
        $this->classSearch->classSearch('composer', self::TEST_NAME)->willReturn([
            [
                'class' => self::TEST_NAME
            ],
        ]);
        $transformed = TextEdits::one(TextEdit::create(0, 0, 'hello'));
        $this->importName->importName(
            SourceCode::fromStringAndPath(self::TEST_SOURCE, self::TEST_PATH),
            ByteOffset::fromInt(self::TEST_OFFSET),
            NameImport::forClass(self::TEST_NAME)
        )->willReturn($transformed);

        /** @var EchoResponse $response */
        $response = $this->handle('import_class', [
            ImportClassHandler::PARAM_OFFSET => self::TEST_OFFSET,
            ImportClassHandler::PARAM_PATH => self::TEST_PATH,
            ImportClassHandler::PARAM_SOURCE => self::TEST_SOURCE
        ]);

        $this->assertInstanceOf(CollectionResponse::class, $response);
    }

    public function testAsksForAliasIfClassAlreadyUsed()
    {
        $this->importName->importName(
            SourceCode::fromStringAndPath(self::TEST_SOURCE, self::TEST_PATH),
            ByteOffset::fromInt(self::TEST_OFFSET),
            NameImport::forClass(self::TEST_NAME)
        )->willThrow(new AliasAlreadyUsedException(NameImport::forClass(self::TEST_NAME, self::TEST_ALIAS)));

        /** @var EchoResponse $response */
        $response = $this->handle('import_class', [
            ImportClassHandler::PARAM_QUALIFIED_NAME => self::TEST_NAME,
            ImportClassHandler::PARAM_OFFSET => self::TEST_OFFSET,
            ImportClassHandler::PARAM_PATH => self::TEST_PATH,
            ImportClassHandler::PARAM_SOURCE => self::TEST_SOURCE
        ]);

        $this->assertInstanceOf(InputCallbackResponse::class, $response);
        $inputs = $response->inputs();
        $this->assertCount(1, $inputs);
        /** @var TextInput $input */
        $input = reset($inputs);
        $this->assertInstanceOf(TextInput::class, $input);
    }

    public function testUsesGivenAlias()
    {
        $transformed = TextEdits::one(TextEdit::create(0, 0, 'hello'));
        $this->importName->importName(
            SourceCode::fromStringAndPath(self::TEST_SOURCE, self::TEST_PATH),
            ByteOffset::fromInt(self::TEST_OFFSET),
            NameImport::forClass(self::TEST_NAME, self::TEST_ALIAS)
        )->willReturn($transformed);

        /** @var EchoResponse $response */
        $response = $this->handle('import_class', [
            ImportClassHandler::PARAM_ALIAS => self::TEST_ALIAS,
            ImportClassHandler::PARAM_QUALIFIED_NAME => self::TEST_NAME,
            ImportClassHandler::PARAM_OFFSET => self::TEST_OFFSET,
            ImportClassHandler::PARAM_PATH => self::TEST_PATH,
            ImportClassHandler::PARAM_SOURCE => self::TEST_SOURCE
        ]);

        $this->assertInstanceOf(CollectionResponse::class, $response);
    }

    public function testShowsMessageIfSelectedClassIsAlreadyImported()
    {
        $this->importName->importName(
            SourceCode::fromStringAndPath(self::TEST_SOURCE, self::TEST_PATH),
            ByteOffset::fromInt(self::TEST_OFFSET),
            NameImport::forClass(self::TEST_NAME)
        )->willThrow(new NameAlreadyImportedException(NameImport::forClass(self::TEST_NAME), self::TEST_NAME));

        /** @var EchoResponse $response */
        $response = $this->handle('import_class', [
            ImportClassHandler::PARAM_QUALIFIED_NAME => self::TEST_NAME,
            ImportClassHandler::PARAM_OFFSET => self::TEST_OFFSET,
            ImportClassHandler::PARAM_PATH => self::TEST_PATH,
            ImportClassHandler::PARAM_SOURCE => self::TEST_SOURCE
        ]);

        $this->assertInstanceOf(EchoResponse::class, $response);
    }
}
