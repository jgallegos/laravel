<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Pool;
use GuzzleHttp\Psr7\Request;

class SimplePostRequest extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:simple-post-request';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Send a simple POST request to https://atomic.incfile.com/fakepost';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        // 4. Ensure reliable delivery
        $client = new GuzzleClient([
            'base_uri' => 'https://atomic.incfile.com/',
        ]);
        
        try {
            $response = $client->request('POST', 'fakepost');
            $code = $response->getStatusCode();
        } catch (ClientException $e) {
            $code = $e->getCode();
            \Log::error($e);
        }

        $success = $this->_handleStatusCode($code);

        if ($success) {
            $this->info("The request reached destination and had a successful response");
        } else {
            $this->info("The request may or may not reached destination and had an error response");
        }
        // 4. End Ensure reliable delivery


        // 5. Handle 100K requests 
        $requests = function ($total) {
            $uri = 'https://atomic.incfile.com/fakepost';
            for ($i = 0; $i < $total; $i++) {
                yield new Request('GET', $uri);
            }
        };

        $totalRequests   = 100000;
        $successRequests = 0;
        $failedRequests  = 0;

        $pool = new Pool($client, $requests($totalRequests), [
            'concurrency' => 100,
            'fulfilled' => function ($response, $index) use (& $successRequests) {
                // this is delivered each successful response
                $successRequests++;
            },
            'rejected' => function ($reason, $index) use (& $failedRequests) {
                // this is delivered each failed request
                $failedRequests++;
            },
        ]);

        // Initiate the transfers and create a promise
        $promise = $pool->promise();

        // Force the pool of requests to complete.
        $promise->wait();
        
        $this->info("Total Requests: $totalRequests");
        $this->info("Success Requests: $successRequests");
        $this->info("Failed Requests: $failedRequests");
        // 5. End Handle 100K requests 

    }

    /**
     * Handle Http Response codes
     * @param $code int
     * @return $success boolean
     */
    private function _handleStatusCode($code)
    {
        $success = false;
        if ($code >= 200 && $code <= 299) {
            $this->info("Success");
            $success = true;
        } elseif ($code >= 300 && $code <= 399) {
            $this->info("Redirects");
        } elseif ($code >= 400 && $code <= 499) {
            $this->info("Client error");
        } elseif ($code >= 500 && $code <= 599) {
            $this->info("Server error");
        }

        return $success;
    }
}
