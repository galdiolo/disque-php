<?php
namespace Disque\Test\Queue;

use DateTime;
use DateTimeZone;
use Disque\Client;
use Disque\Queue\Job;
use Disque\Queue\JobInterface;
use Disque\Queue\Queue;
use Disque\Queue\Marshal\MarshalerInterface;
use InvalidArgumentException;
use Mockery as m;
use PHPUnit_Framework_TestCase;
use Disque\Command\Response\JobsResponse AS Response;
use Disque\Command\Response\JobsWithCountersResponse AS Counters;


class MockJob extends Job
{
}

class QueueTest extends PHPUnit_Framework_TestCase
{
    public function tearDown()
    {
        parent::tearDown();
        m::close();
    }

    public function testGetName()
    {
        $name = 'queue';
        $q = new Queue(new Client(), $name);
        $this->assertSame($name, $q->getName());
    }

    public function testInstance()
    {
        $q = new Queue(new Client(), 'queue');
        $this->assertInstanceOf(Queue::class, $q);
    }

    public function testMarshaler()
    {
        $q = new Queue(new Client(), 'queue');
        $q->setMarshaler(m::mock(MarshalerInterface::class));
    }

    public function testPushConnected()
    {
        $payload = ['test' => 'stuff'];
        $job = m::mock(Job::class.'[setId]')
            ->shouldReceive('setId')
            ->with('JOB_ID')
            ->once()
            ->mock();
        $job->setBody($payload);

        $client = m::mock(Client::class)
            ->shouldReceive('isConnected')
            ->with()
            ->andReturn(true)
            ->once()
            ->shouldReceive('addJob')
            ->with('queue', json_encode($payload), [])
            ->andReturn('JOB_ID')
            ->mock();

        $q = new Queue($client, 'queue');
        $result = $q->push($job);
        $this->assertSame($job, $result);
    }

    public function testPushNotConnected()
    {
        $payload = ['test' => 'stuff'];
        $job = m::mock(Job::class.'[setId]')
            ->shouldReceive('setId')
            ->with('JOB_ID')
            ->once()
            ->mock();
        $job->setBody($payload);

        $client = m::mock(Client::class)
            ->shouldReceive('isConnected')
            ->with()
            ->andReturn(false)
            ->once()
            ->shouldReceive('connect')
            ->with()
            ->once()
            ->shouldReceive('addJob')
            ->with('queue', json_encode($payload), [])
            ->andReturn('JOB_ID')
            ->mock();

        $q = new Queue($client, 'queue');
        $result = $q->push($job);
        $this->assertSame($job, $result);
    }

    public function testPushWithOptions()
    {
        $payload = ['test' => 'stuff'];
        $job = m::mock(Job::class.'[setId]')
            ->shouldReceive('setId')
            ->with('JOB_ID')
            ->once()
            ->mock();
        $job->setBody($payload);

        $client = m::mock(Client::class)
            ->shouldReceive('isConnected')
            ->with()
            ->andReturn(true)
            ->once()
            ->shouldReceive('addJob')
            ->with('queue', json_encode($payload), ['delay' => 3000])
            ->andReturn('JOB_ID')
            ->mock();

        $q = new Queue($client, 'queue');
        $result = $q->push($job, ['delay' => 3000]);
        $this->assertSame($job, $result);
    }

    public function testPushCustomMarshaler()
    {
        $payload = ['test' => 'stuff'];
        $job = m::mock(Job::class.'[setId]')
            ->shouldReceive('setId')
            ->with('JOB_ID')
            ->once()
            ->mock();
        $job->setBody($payload);

        $client = m::mock(Client::class)
            ->shouldReceive('isConnected')
            ->with()
            ->andReturn(true)
            ->once()
            ->shouldReceive('addJob')
            ->with('queue', json_encode($payload), [])
            ->andReturn('JOB_ID')
            ->mock();

        $marshaler = m::mock(MarshalerInterface::class)
            ->shouldReceive('marshal')
            ->with($job)
            ->andReturn(json_encode($payload))
            ->once()
            ->mock();

        $q = new Queue($client, 'queue');
        $q->setMarshaler($marshaler);
        $result = $q->push($job);
        $this->assertSame($job, $result);
    }

    public function testProcessedConnected()
    {
        $job = m::mock(JobInterface::class)
            ->shouldReceive('getId')
            ->with()
            ->andReturn('JOB_ID')
            ->once()
            ->mock();

        $client = m::mock(Client::class)
            ->shouldReceive('isConnected')
            ->with()
            ->andReturn(true)
            ->once()
            ->shouldReceive('ackJob')
            ->with('JOB_ID')
            ->mock();

        $q = new Queue($client, 'queue');
        $q->processed($job);
    }

    public function testProcessedNotConnected()
    {
        $job = m::mock(JobInterface::class)
            ->shouldReceive('getId')
            ->with()
            ->andReturn('JOB_ID')
            ->once()
            ->mock();

        $client = m::mock(Client::class)
            ->shouldReceive('isConnected')
            ->with()
            ->andReturn(false)
            ->once()
            ->shouldReceive('connect')
            ->with()
            ->once()
            ->shouldReceive('ackJob')
            ->with('JOB_ID')
            ->mock();

        $q = new Queue($client, 'queue');
        $q->processed($job);
    }

    public function testFailedConnected()
    {
        $job = m::mock(JobInterface::class)
            ->shouldReceive('getId')
            ->with()
            ->andReturn('JOB_ID')
            ->once()
            ->mock();

        $client = m::mock(Client::class)
            ->shouldReceive('isConnected')
            ->with()
            ->andReturn(true)
            ->once()
            ->shouldReceive('nack')
            ->with('JOB_ID')
            ->mock();

        $q = new Queue($client, 'queue');
        $q->failed($job);
    }

    public function testFailedNotConnected()
    {
        $job = m::mock(JobInterface::class)
            ->shouldReceive('getId')
            ->with()
            ->andReturn('JOB_ID')
            ->once()
            ->mock();

        $client = m::mock(Client::class)
            ->shouldReceive('isConnected')
            ->with()
            ->andReturn(false)
            ->once()
            ->shouldReceive('connect')
            ->with()
            ->once()
            ->shouldReceive('nack')
            ->with('JOB_ID')
            ->mock();

        $q = new Queue($client, 'queue');
        $q->failed($job);
    }

    public function testPullConnected()
    {
        $options = ['timeout' => 0, 'count' => 1, 'withcounters' => true];
        $payload = ['test' => 'stuff'];
        $jobId = 'JOB_ID';
        $nacks = 1;
        $ad = 2;

        $client = m::mock(Client::class)
            ->shouldReceive('isConnected')
            ->with()
            ->andReturn(true)
            ->once()
            ->shouldReceive('getJob')
            ->with('queue', $options)
            ->andReturn([
                [
                    Response::KEY_ID => $jobId,
                    Response::KEY_BODY => json_encode($payload),
                    Counters::KEY_NACKS => $nacks,
                    Counters::KEY_ADDITIONAL_DELIVERIES => $ad
                ]
            ])
            ->mock();

        $q = new Queue($client, 'queue');
        $job = $q->pull();
        $this->assertSame($jobId, $job->getId());
        $this->assertSame($payload, $job->getBody());
        $this->assertSame($nacks, $job->getNacks());
        $this->assertSame($ad, $job->getAdditionalDeliveries());
    }

    public function testPullNotConnected()
    {
        $options = ['timeout' => 0, 'count' => 1, 'withcounters' => true];
        $payload = ['test' => 'stuff'];
        $jobId = 'JOB_ID';
        $nacks = 1;
        $ad = 2;

        $client = m::mock(Client::class)
            ->shouldReceive('isConnected')
            ->with()
            ->andReturn(false)
            ->once()
            ->shouldReceive('connect')
            ->with()
            ->once()
            ->shouldReceive('getJob')
            ->with('queue', $options)
            ->andReturn([
                [
                    Response::KEY_ID => $jobId,
                    Response::KEY_BODY => json_encode($payload),
                    Counters::KEY_NACKS => $nacks,
                    Counters::KEY_ADDITIONAL_DELIVERIES => $ad
                ]
            ])
            ->mock();

        $q = new Queue($client, 'queue');
        $job = $q->pull();
        $this->assertInstanceOf(Job::class, $job);
        $this->assertSame($jobId, $job->getId());
        $this->assertSame($payload, $job->getBody());
        $this->assertSame($nacks, $job->getNacks());
        $this->assertSame($ad, $job->getAdditionalDeliveries());
    }

    public function testPullCustomMarshaler()
    {
        $options = ['timeout' => 0, 'count' => 1, 'withcounters' => true];
        $payload = ['test' => 'stuff'];
        $jobId = 'JOB_ID';
        $nacks = 1;
        $ad = 2;

        $client = m::mock(Client::class)
            ->shouldReceive('isConnected')
            ->with()
            ->andReturn(true)
            ->once()
            ->shouldReceive('getJob')
            ->with('queue', $options)
            ->andReturn([
                [
                    Response::KEY_ID => $jobId,
                    Response::KEY_BODY => json_encode($payload),
                    Counters::KEY_NACKS => $nacks,
                    Counters::KEY_ADDITIONAL_DELIVERIES => $ad
                ]
            ])
            ->mock();

        $job = new Job();
        $marshaler = m::mock(MarshalerInterface::class)
            ->shouldReceive('unmarshal')
            ->with(json_encode($payload))
            ->andReturn($job)
            ->once()
            ->mock();

        $q = new Queue($client, 'queue');
        $q->setMarshaler($marshaler);
        $result = $q->pull();
        $this->assertSame($job, $result);
    }

    public function testPullSeveralJobs()
    {
        $options = ['timeout' => 0, 'count' => 1, 'withcounters' => true];
        $payload = ['test' => 'stuff'];
        $jobId = 'JOB_ID';
        $nacks = 1;
        $ad = 2;
        $jobId2 = 'JOB_ID2';

        $client = m::mock(Client::class)
            ->shouldReceive('isConnected')
            ->with()
            ->andReturn(true)
            ->once()
            ->shouldReceive('getJob')
            ->with('queue', $options)
            ->andReturn([
                [
                    Response::KEY_ID => $jobId,
                    Response::KEY_BODY => json_encode($payload),
                    Counters::KEY_NACKS => $nacks,
                    Counters::KEY_ADDITIONAL_DELIVERIES => $ad
                ],
                [
                    Response::KEY_ID => $jobId2,
                    Response::KEY_BODY => json_encode([]),
                    Counters::KEY_NACKS => $nacks,
                    Counters::KEY_ADDITIONAL_DELIVERIES => $ad
                ],
            ])
            ->mock();

        $q = new Queue($client, 'queue');
        $job = $q->pull();
        $this->assertSame($jobId, $job->getId());
        $this->assertSame($payload, $job->getBody());
        $this->assertSame($nacks, $job->getNacks());
        $this->assertSame($ad, $job->getAdditionalDeliveries());
    }

    public function testPullNoJobs()
    {
        $options = ['timeout' => 0, 'count' => 1, 'withcounters' => true];

        $client = m::mock(Client::class)
            ->shouldReceive('isConnected')
            ->with()
            ->andReturn(true)
            ->once()
            ->shouldReceive('getJob')
            ->with('queue', $options)
            ->andReturn([])
            ->mock();

        $q = new Queue($client, 'queue');

        $job = $q->pull();
        $this->assertNull($job);
    }

    public function testPullNoJobsWithTimeout()
    {
        $timeout = 3000;
        $options = ['timeout' => $timeout, 'count' => 1, 'withcounters' => true];

        $client = m::mock(Client::class)
            ->shouldReceive('isConnected')
            ->with()
            ->andReturn(true)
            ->once()
            ->shouldReceive('getJob')
            ->with('queue', $options)
            ->andReturn([])
            ->mock();

        $q = new Queue($client, 'queue');

        $job = $q->pull($timeout);
        $this->assertNull($job);
    }

    public function testScheduleInvalidDateInPast()
    {
        $this->setExpectedException(InvalidArgumentException::class, 'Specified schedule time has passed');
        $date = new DateTime('-10 seconds');
        $q = new Queue(m::mock(Client::class), 'queue');
        $q->schedule(new Job(), $date);
    }

    /**
     * This and the following tests test scheduling jobs in the future
     *
     * They depend on the global system time and contain a race condition
     * wherein system time can move forward one second between the two DateTime()
     * instantiations (the first one in the test, the second one in the
     * schedule() method). If that is the case, the resulting delay is one
     * second off (eg. 9 seconds instead of the scheduled 10 seconds).
     *
     * For that reason the mocked method expects either the $delay (10 seconds)
     * or $delay - 1 (9 seconds). Both are valid test results.
     */
    public function testScheduleDefaultTimeZone()
    {
        $delay = 10;

        $job = new Job();
        $queue = m::mock(Queue::class.'[push]', [m::mock(Client::class), 'queue'])
            ->shouldReceive('push')
            ->with($job,
                anyOf(
                    ['delay' => $delay],
                    ['delay' => $delay - 1]
                ))
            ->andReturn($job)
            ->once()
            ->mock();

        $result = $queue->schedule($job, new DateTime('+' . $delay . ' seconds', new DateTimeZone(Queue::DEFAULT_JOB_TIMEZONE)));
        $this->assertSame($job, $result);
    }

    public function testScheduleDifferentTimeZone()
    {
        $delay = 10;

        $job = new Job();
        $queue = m::mock(Queue::class.'[push]', [m::mock(Client::class), 'queue'])
            ->shouldReceive('push')
            ->with($job,
                anyOf(
                    ['delay' => $delay],
                    ['delay' => $delay - 1]
                ))
            ->andReturn($job)
            ->once()
            ->mock();

        $timeZone = new DateTimeZone('America/Argentina/Buenos_Aires');
        $this->assertNotSame(Queue::DEFAULT_JOB_TIMEZONE, $timeZone->getName());
        $result = $queue->schedule($job, new DateTime('+' . $delay . ' seconds', $timeZone));
        $this->assertSame($job, $result);
    }

    public function testScheduleWayInTheFuture()
    {
        $delayDays = 25;
        $delay = ($delayDays * 24 * 60 * 60);

        $job = new Job();
        $queue = m::mock(Queue::class.'[push]', [m::mock(Client::class), 'queue'])
            ->shouldReceive('push')
            ->with($job,
                anyOf(
                    ['delay' => $delay],
                    ['delay' => $delay - 1]
                ))
            ->andReturn($job)
            ->once()
            ->mock();

        $result = $queue->schedule($job, new DateTime('+' . $delayDays . ' days', new DateTimeZone(Queue::DEFAULT_JOB_TIMEZONE)));
        $this->assertSame($job, $result);
    }

    public function testScheduleWithOptions()
    {
        $delay = 10;

        $job = new Job();
        $queue = m::mock(Queue::class.'[push]', [m::mock(Client::class), 'queue'])
            ->shouldReceive('push')
            ->with($job,
                anyOf(
                    ['retry' => 1000, 'delay' => $delay],
                    ['retry' => 1000, 'delay' => $delay - 1]
                ))
            ->andReturn($job)
            ->once()
            ->mock();

        $when = new DateTime('+' . $delay . ' seconds', new DateTimeZone(Queue::DEFAULT_JOB_TIMEZONE));
        $result = $queue->schedule($job, $when, ['retry' => 1000]);
        $this->assertSame($job, $result);
    }

    public function testProcessingConnected()
    {
        $job = m::mock(JobInterface::class)
            ->shouldReceive('getId')
            ->with()
            ->andReturn('JOB_ID')
            ->once()
            ->mock();

        $client = m::mock(Client::class)
            ->shouldReceive('isConnected')
            ->with()
            ->andReturn(true)
            ->once()
            ->shouldReceive('working')
            ->with('JOB_ID')
            ->andReturn(3)
            ->mock();

        $q = new Queue($client, 'queue');
        $result = $q->processing($job);
        $this->assertSame(3, $result);
    }

    public function testWorkingNotConnected()
    {
        $job = m::mock(JobInterface::class)
            ->shouldReceive('getId')
            ->with()
            ->andReturn('JOB_ID')
            ->once()
            ->mock();

        $client = m::mock(Client::class)
            ->shouldReceive('isConnected')
            ->with()
            ->andReturn(false)
            ->once()
            ->shouldReceive('connect')
            ->with()
            ->once()
            ->shouldReceive('working')
            ->with('JOB_ID')
            ->andReturn(3)
            ->mock();

        $q = new Queue($client, 'queue');
        $result = $q->processing($job);
        $this->assertSame(3, $result);
    }
}