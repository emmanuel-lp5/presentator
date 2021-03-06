<?php
namespace presentator\api\tests\functional;

use presentator\api\tests\FunctionalTester;
use presentator\api\tests\fixtures\UserFixture;
use presentator\api\tests\fixtures\ProjectFixture;
use presentator\api\tests\fixtures\PrototypeFixture;
use presentator\api\tests\fixtures\UserProjectRelFixture;
use presentator\api\models\User;
use presentator\api\models\Prototype;

/**
 * PrototypesController API functional tests.
 *
 * @author Gani Georgiev <gani.georgiev@gmail.com>
 */
class PrototypesCest
{
    /**
     * {@inheritdoc}
     */
    public function _before(FunctionalTester $I)
    {
        $I->haveFixtures([
            'ProjectFixture' => [
                'class' => ProjectFixture::class,
            ],
            'PrototypeFixture' => [
                'class' => PrototypeFixture::class,
            ],
            'UserFixture' => [
                'class' => UserFixture::class,
            ],
            'UserProjectRelFixture' => [
                'class' => UserProjectRelFixture::class,
            ],
        ]);
    }

    /* `PrototypesController::actionIndex()`
    --------------------------------------------------------------- */
    /**
     * `PrototypesController::actionIndex()` failure test.
     *
     * @param FunctionalTester $I
     */
    public function indexFailure(FunctionalTester $I)
    {
        $I->wantTo('Unsuccessfully list prototypes');

        $I->amGoingTo('try accessing the action unauthorized');
        $I->sendGET('/prototypes');
        $I->seeUnauthorizedResponse();
    }

    /**
     * `PrototypesController::actionIndex()` success test.
     *
     * @param FunctionalTester $I
     */
    public function indexSuccess(FunctionalTester $I)
    {
        $I->wantTo('Successfully list prototypes');

        $regularUser = User::findOne(1002);
        $superUser   = User::findOne(['status' => User::STATUS['ACTIVE'], 'type' => User::TYPE['SUPER']]);

        $I->amGoingTo('authorize as regular user');
        $I->haveHttpHeader('Authorization', 'Bearer ' . $regularUser->generateAccessToken());
        $I->sendAndCheckDataProviderResponses('/prototypes', [
            [
                'params'   => [],
                'expected' => [1001, 1002, 1003],
            ],
            [
                'params'   => ['per-page' => 1, 'page' => 2],
                'expected' => [1002],
            ],
            [
                'params'   => ['search[projectId]' => 1001],
                'expected' => [1001, 1002],
            ],
            [
                'params'   => ['sort' => '-title'],
                'expected' => [1003, 1002, 1001],
            ],
        ]);

        $I->amGoingTo('authorize as super user');
        $I->haveHttpHeader('Authorization', 'Bearer ' . $superUser->generateAccessToken());
        $I->sendAndCheckDataProviderResponses('/prototypes', [
            [
                'params'   => [],
                'expected' => [1001, 1002, 1003, 1004, 1005, 1006],
            ],
            [
                'params'   => ['per-page' => 2, 'page' => 2],
                'expected' => [1003, 1004],
            ],
            [
                'params'   => ['search[projectId]' => 1003],
                'expected' => [1004],
            ],
            [
                'params'   => ['sort' => '-title'],
                'expected' => [1006, 1005, 1004, 1003, 1002, 1001],
            ],
        ]);
    }

    /* `PrototypesController::actionCreate()`
    --------------------------------------------------------------- */
    /**
     * `PrototypesController::actionCreate()` failure test.
     *
     * @param FunctionalTester $I
     */
    public function createFailure(FunctionalTester $I)
    {
        $I->wantTo('Unsuccessfully create new prototype');

        $user = User::findOne(1002);

        $I->amGoingTo('try accessing the action unauthorized');
        $I->sendPOST('/prototypes');
        $I->seeUnauthorizedResponse();

        $I->amGoingTo('authorize and submit invalid form data');
        $I->haveHttpHeader('Authorization', 'Bearer ' . $user->generateAccessToken());
        $I->sendPOST('/prototypes', [
            'projectId'   => 1005,
            'type'        => 'invalid',
            'title'       => '',
            'width'       => -10,
            'height'      => -10,
            'scaleFactor' => -10,
        ]);
        $I->seeResponseCodeIs(400);
        $I->seeResponseIsJson();
        $I->seeResponseMatchesJsonType([
            'message' => 'string',
            'errors'  => [
                'projectId'   => 'string',
                'type'        => 'string',
                'title'       => 'string',
                'width'       => 'string',
                'height'      => 'string',
                'scaleFactor' => 'string',
            ],
        ]);
    }

    /**
     * `PrototypesController::actionCreate()` success test.
     *
     * @param FunctionalTester $I
     */
    public function createSuccess(FunctionalTester $I)
    {
        $I->wantTo('Successfully create new prototype');

        $regularUser = User::findOne(1002);
        $superUser   = User::findOne(['status' => User::STATUS['ACTIVE'], 'type' => User::TYPE['SUPER']]);

        $testScenarios = [
            [
                'comment' => 'authorize as regular user and create a new prototype for an owned project',
                'token'   => $regularUser->generateAccessToken(),
                'data'    => [
                    'projectId'   => 1001,
                    'type'        => 'desktop',
                    'title'       => 'create_test',
                    'width'       => 0,
                    'height'      => 0,
                    'scaleFactor' => 0.5,
                ],
            ],
            [
                'comment' => 'authorize as super user and create a new prototype for a project',
                'token'   => $superUser->generateAccessToken(),
                'data'    => [
                    'projectId' => 1005,
                    'type'      => 'mobile',
                    'title'     => 'create_test2',
                    'width'     => 100,
                    'height'    => 300,
                ],
            ],
        ];

        foreach ($testScenarios as $scenario) {
            $I->haveHttpHeader('Authorization', 'Bearer ' . $scenario['token']);
            $I->sendPOST('/prototypes', $scenario['data']);
            $I->seeResponseCodeIs(200);
            $I->seeResponseMatchesJsonType([
                'projectId'   => 'integer',
                'title'       => 'string',
                'type'        => 'string',
                'scaleFactor' => 'float|integer',
            ]);
            $I->seeResponseContainsJson($scenario['data']);
        }
    }

    /* `PrototypesController::actionUpdate()`
    --------------------------------------------------------------- */
    /**
     * `PrototypesController::actionUpdate()` failure test.
     *
     * @param FunctionalTester $I
     */
    public function updateFailure(FunctionalTester $I)
    {
        $I->wantTo('Unsuccessfully update prototype');

        $regularUser = User::findOne(1002);
        $superUser   = User::findOne(['status' => User::STATUS['ACTIVE'], 'type' => User::TYPE['SUPER']]);

        $I->amGoingTo('try accessing the action unauthorized');
        $I->sendPUT('/prototypes/1006');
        $I->seeUnauthorizedResponse();

        $I->amGoingTo('authorize as regular user and try to update prototype owned by another user');
        $I->haveHttpHeader('Authorization', 'Bearer ' . $regularUser->generateAccessToken());
        $I->sendPUT('/prototypes/1006');
        $I->seeNotFoundResponse();

        $I->amGoingTo('authorize as super user and submit invalid form data to a random prototype');
        $I->haveHttpHeader('Authorization', 'Bearer ' . $superUser->generateAccessToken());
        $I->sendPUT('/prototypes/1006', [
            'projectId'   => 123456,
            'type'        => 'invalid',
            'title'       => '',
            'width'       => -10,
            'height'      => -10,
            'scaleFactor' => -10,
        ]);
        $I->seeResponseCodeIs(400);
        $I->seeResponseIsJson();
        $I->seeResponseMatchesJsonType([
            'message' => 'string',
            'errors'  => [
                'projectId'   => 'string',
                'type'        => 'string',
                'title'       => 'string',
                'width'       => 'string',
                'height'      => 'string',
                'scaleFactor' => 'string',
            ],
        ]);
    }

    /**
     * `PrototypesController::actionUpdate()` success test.
     *
     * @param FunctionalTester $I
     */
    public function updateSuccess(FunctionalTester $I)
    {
        $I->wantTo('Successfully update prototype');

        $regularUser = User::findOne(1002);
        $superUser   = User::findOne(['status' => User::STATUS['ACTIVE'], 'type' => User::TYPE['SUPER']]);

        $testScenarios = [
            [
                'comment'     => 'authorize as regular user and update owned prototype',
                'token'       => $regularUser->generateAccessToken(),
                'prototypeId' => 1001,
                'data'        => [
                    'projectId'   => 1002,
                    'type'        => 'mobile',
                    'title'       => 'update_test',
                    'width'       => 200,
                    'height'      => 300,
                    'scaleFactor' => 2.5,
                ],
            ],
            [
                'comment'     => 'authorize as super user and update a prototype',
                'token'       => $superUser->generateAccessToken(),
                'prototypeId' => 1006,
                'data'        => [
                    'projectId'   => 1003,
                    'title'       => 'update_test',
                    'scaleFactor' => 0.5,
                ],
            ],
        ];

        foreach ($testScenarios as $scenario) {
            $I->haveHttpHeader('Authorization', 'Bearer ' . $scenario['token']);
            $I->sendPUT('/prototypes/' . $scenario['prototypeId'], $scenario['data']);
            $I->seeResponseCodeIs(200);
            $I->seeResponseMatchesJsonType([
                'id'          => ('integer:=' . $scenario['prototypeId']),
                'projectId'   => 'integer',
                'title'       => 'string',
                'type'        => 'string',
                'scaleFactor' => 'float|integer',
            ]);
            $I->seeResponseContainsJson($scenario['data']);
        }
    }

    /* `PrototypesController::actionView()`
    --------------------------------------------------------------- */
    /**
     * `PrototypesController::actionView()` failure test.
     *
     * @param FunctionalTester $I
     */
    public function viewFailure(FunctionalTester $I)
    {
        $I->wantTo('Unsuccessfully view prototype');

        $regularUser = User::findOne(1002);
        $superUser   = User::findOne(['status' => User::STATUS['ACTIVE'], 'type' => User::TYPE['SUPER']]);

        $I->amGoingTo('try accessing the action unauthorized');
        $I->sendGET('/prototypes/1006');
        $I->seeUnauthorizedResponse();

        $I->amGoingTo('authorize as regular user and try to view prototype owned by another user');
        $I->haveHttpHeader('Authorization', 'Bearer ' . $regularUser->generateAccessToken());
        $I->sendGET('/prototypes/1006');
        $I->seeNotFoundResponse();

        $I->amGoingTo('authorize as super user and try to view unexisting prototype');
        $I->haveHttpHeader('Authorization', 'Bearer ' . $superUser->generateAccessToken());
        $I->sendGET('/prototypes/123456');
        $I->seeNotFoundResponse();
    }

    /**
     * `PrototypesController::actionView()` success test.
     *
     * @param FunctionalTester $I
     */
    public function viewSuccess(FunctionalTester $I)
    {
        $I->wantTo('Successfully view prototype');

        $regularUser  = User::findOne(1002);
        $superUser    = User::findOne(['status' => User::STATUS['ACTIVE'], 'type' => User::TYPE['SUPER']]);

        $testScenarios = [
            [
                'comment'     => 'authorize as regular user and try to view owned prototype',
                'token'       => $regularUser->generateAccessToken(),
                'prototypeId' => 1001,
            ],
            [
                'comment'     => 'authorize as super user and try to view a prototype',
                'token'       => $superUser->generateAccessToken(),
                'prototypeId' => 1004,
            ],
        ];

        foreach ($testScenarios as $scenario) {
            $I->amGoingTo($scenario['comment']);
            $I->haveHttpHeader('Authorization', 'Bearer ' . $scenario['token']);
            $I->sendGET('/prototypes/' . $scenario['prototypeId'], ['expand' => 'screens']);
            $I->seeResponseCodeIs(200);
            $I->seeResponseIsJson();
            $I->seeResponseMatchesJsonType([
                'id'      => 'integer:=' . $scenario['prototypeId'],
                'screens' => 'array',
            ]);
        }
    }

    /* `PrototypesController::actionDelete()`
    --------------------------------------------------------------- */
    /**
     * `PrototypesController::actionDelete()` failure test.
     *
     * @param FunctionalTester $I
     */
    public function deleteFailure(FunctionalTester $I)
    {
        $I->wantTo('Unsuccessfully delete prototype');

        $regularUser = User::findOne(1002);
        $superUser   = User::findOne(['status' => User::STATUS['ACTIVE'], 'type' => User::TYPE['SUPER']]);

        $I->amGoingTo('try accessing the action unauthorized');
        $I->sendDELETE('/prototypes/1006');
        $I->seeUnauthorizedResponse();

        $I->amGoingTo('authorize as regular user and try to delete prototype owned by another user');
        $I->haveHttpHeader('Authorization', 'Bearer ' . $regularUser->generateAccessToken());
        $I->sendDELETE('/prototypes/1006');
        $I->seeNotFoundResponse();

        $I->amGoingTo('authorize as super user and try to delete unexisting prototype');
        $I->haveHttpHeader('Authorization', 'Bearer ' . $superUser->generateAccessToken());
        $I->sendDELETE('/prototypes/123456');
        $I->seeNotFoundResponse();
    }

    /**
     * `PrototypesController::actionDelete()` success test.
     *
     * @param FunctionalTester $I
     */
    public function deleteSuccess(FunctionalTester $I)
    {
        $I->wantTo('Successfully delete prototype');

        $regularUser  = User::findOne(1002);
        $superUser    = User::findOne(['status' => User::STATUS['ACTIVE'], 'type' => User::TYPE['SUPER']]);

        $testScenarios = [
            [
                'comment'     => 'authorize as regular user and try to delete an owned prototype',
                'token'       => $regularUser->generateAccessToken(),
                'prototypeId' => 1002,
            ],
            [
                'comment'     => 'authorize as super user and try to delete a prototype',
                'token'       => $superUser->generateAccessToken(),
                'prototypeId' => 1005,
            ],
        ];

        foreach ($testScenarios as $scenario) {
            $I->amGoingTo($scenario['comment']);
            $I->haveHttpHeader('Authorization', 'Bearer ' . $scenario['token']);
            $I->sendDELETE('/prototypes/' . $scenario['prototypeId']);
            $I->seeResponseCodeIs(204);
            $I->dontSeeRecord(Prototype::class, ['id' => $scenario['prototypeId']]);
        }
    }
}
