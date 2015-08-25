<?php
namespace Bart\Gerrit;

use Bart\BaseTestCase;

class ChangeTest extends BaseTestCase
{
	private $fakeChangeId = 'Iabc123';
	private $fakeMergedHash = 'a7dd3f9';
	private $changeNum = '2583';

	public function testCurrentPatchSet()
	{
		$stubApi = $this->getStubApiForDefaultRemoteData();
		$this->registerDiesel('\Bart\Gerrit\Api', $stubApi);

		$change = new Change($this->fakeChangeId);

		$this->assertEquals(1, $change->currentPatchSetNumber());
	}

	public function testIsChangeApprovedAndVerified()
	{
		$this->getStubApiForApprovedIsRequired();

		$change = new Change($this->fakeChangeId);
		$this->assertTrue($change->isReviewedAndVerified(), 'Is reviewed?');
	}

	public function testMarkMerged()
	{
		$stubApi = $this->getStubApiForDefaultRemoteData();
		$stubApi->expects($this->exactly(2))
			->method('gsql')
			->will($this->returnCallback(function ($gsql, array $params) {
				$apiResult = new ApiResult(array('rowCount' => 1), array());
				if (strstr($gsql, 'UPDATE changes')) {
					$this->assertCount(2, $params, 'UPDATE params');
					$this->assertEquals(2, $params[0], 'patch set count');
					$this->assertEquals($this->fakeChangeId, $params[1], 'change id');

					return $apiResult;
				} else if (strstr($gsql, 'INSERT INTO')) {
					$this->assertCount(4, $params, 'INSERT params');

					$this->assertEquals($this->fakeMergedHash, $params[0]);
					$this->assertEquals(9, $params[1]);
					$this->assertEquals($this->changeNum, $params[2]);
					$this->assertEquals(2, $params[3]);

					return $apiResult;
				}

				$this->fail('Unexpected GSQL sent to Api::gsql()  - ' . $gsql);
			}));

		$this->registerDiesel('\Bart\Gerrit\Api', $stubApi);

		$change = new Change($this->fakeChangeId);
		$change->markMerged($this->fakeMergedHash);
	}

	public function testNoMatchForChangeId()
	{
		$stubApi = $this->getStubApiForDefaultRemoteData(0);
		$this->registerDiesel('\Bart\Gerrit\Api', $stubApi);

		$change = new Change($this->fakeChangeId);

		$this->assertFalse($change->exists(), 'Change exists?');
	}

	public function testValidChangeExists()
	{
		$stubApi = $this->getStubApiForDefaultRemoteData();
		$this->registerDiesel('\Bart\Gerrit\Api', $stubApi);

		$change = new Change($this->fakeChangeId);

		$this->assertTrue($change->exists(), 'Change exists?');
	}

	public function testAbandoning()
	{
		$stubApi = $this->getStubApiForDefaultRemoteData();
		$stubApi->expects($this->exactly(1))
			->method('review')
			->with($this->changeNum . ',1', null, 'See ya later', '--abandon')
			->will($this->returnValue(new ApiResult(array('rowCount' => 1), array())));
		$this->registerDiesel('\Bart\Gerrit\Api', $stubApi);

		$change = new Change($this->fakeChangeId);
		$change->abandon('See ya later');
	}

	public function testCommenting()
	{
		$stubApi = $this->getStubApiForDefaultRemoteData();
		$stubApi->expects($this->exactly(1))
			->method('review')
			->with($this->changeNum . ',1', null, 'See ya later')
			->will($this->returnValue(new ApiResult(array('rowCount' => 1), array())));
		$this->registerDiesel('\Bart\Gerrit\Api', $stubApi);

		$change = new Change($this->fakeChangeId);
		$change->comment('See ya later');
	}

	/**
	 * @param int $rowCount 0 or 1 number of records to return
	 * @return \PHPUnit_Framework_MockObject_MockObject stub Gerrit\Api
	 */
	private function getStubApiForDefaultRemoteData($rowCount = 1)
	{
		$gerritData = $rowCount == 1 ?
			[
				'number' => $this->changeNum,
				'currentPatchSet' => ['number' => '1'],
			] : [];

		$stubApi = $this->getMock('\Bart\Gerrit\Api', [], [], '', false);
		$stubApi->expects($this->once())
			->method('query')
			->with('--current-patch-set %s', [$this->fakeChangeId])
			->will($this->returnValue(new ApiResult(['rowCount' => $rowCount], [$gerritData])));

		return $stubApi;
	}

	/**
	 * Stub out a successful call to Gerrit for an approved and verified change
	 */
	private function getStubApiForApprovedIsRequired()
	{
		$this->shmockAndDieselify('\Bart\Gerrit\Api', function($stubApi) {
			$gerritData =  [
				'number' => $this->changeNum,
				'currentPatchSet' => ['number' => '1'],
			];

			$stubApi->query(
				'--current-patch-set %s %s %s %s %s', [
					$this->fakeChangeId,
					'label:Code-Review+2',
					'label:Verified+1',
					'NOT label:Code-Review-2',
					'NOT label:Verified-1',
				])->once()->return_value(new ApiResult(['rowCount' => 1], [$gerritData]));
		}, true);
	}
}

