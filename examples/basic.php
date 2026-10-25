<?php

declare(strict_types=1);

/*
 * Lists your projects and the open tasks of the first one, then creates a task.
 *
 *   composer require kodvalley/improj-php guzzlehttp/guzzle
 *   IMPROJ_TOKEN=improj_pat_... php examples/basic.php
 */

require __DIR__ . '/../vendor/autoload.php';

use GuzzleHttp\Client;
use GuzzleHttp\Psr7\HttpFactory;
use Kodvalley\Improj\Exception\ImprojException;
use Kodvalley\Improj\Improj;
use Kodvalley\Improj\RequestOptions;

$factory = new HttpFactory();
$improj = new Improj(
    getenv('IMPROJ_TOKEN') ?: null,
    // Timeouts belong to the HTTP client (PSR-18 has none). Guzzle's sendRequest() never follows redirects.
    httpClient: new Client(['timeout' => 30, 'connect_timeout' => 10]),
    requestFactory: $factory,
    streamFactory: $factory,
);

try {
    $me = $improj->me->get();
    printf("Signed in as %s (token scopes: %s)\n", $me->name ?? 'unknown', implode(', ', $me->token->scopes));

    $first = null;
    foreach ($improj->projects->all() as $project) {
        printf("#%d %s%s\n", $project->id, $project->acronym !== null ? "{$project->acronym}: " : '', $project->name);
        $first ??= $project;
    }
    if ($first === null) {
        exit(0);
    }

    foreach ($improj->tasks->all($first->id, ['status' => 'todo']) as $task) {
        printf("  [ ] %s%s\n", $task->title, $task->dueDate !== null ? " (due {$task->dueDate})" : '');
    }

    // A stable Idempotency-Key makes this safe to run twice: the second run gets the first answer back.
    $task = $improj->tasks->create($first->id, ['title' => 'Check the API integration', 'priority' => 'low'], new RequestOptions(idempotencyKey: 'example-basic-' . date('Y-m-d')));
    printf("Created task #%d\n", $task->id);
} catch (ImprojException $e) {
    // getMessage() is redacted; quote the request id when you contact support.
    fprintf(\STDERR, "%s (status %d, code %s, request %s)\n", $e->getMessage(), $e->getStatus(), $e->getErrorCode(), $e->getRequestId() ?? '-');
    exit(1);
}
