<?php

use App\Models\Form;
use App\Models\FormSession;
use App\Models\FormIntegration;
use App\Models\FormSessionResponse;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use App\Events\FormSessionCompletedEvent;
use App\Jobs\CallWebhookJob;
use App\Listeners\FormSubmitWebhookListener;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('submitting a session triggers all integrations on a form', function () {
    Queue::fake();

    $form = Form::factory()
        ->has(FormIntegration::factory([
            'webhook_method' => 'GET',
            'webhook_url' => 'https://void.work/submit'
        ]))
        ->has(FormIntegration::factory([
            'webhook_method' => 'GET',
            'webhook_url' => 'https://blackhole.wip/submit'
        ]))
        ->create();

    $session = FormSession::factory()->for($form)
        ->has(FormSessionResponse::factory([
            'value' => 'test response'
        ]))
        ->completed()
        ->create();

    // emulate the listener reacting to the FormSessionCompletedEvent
    with(new FormSubmitWebhookListener)
        ->handle(new FormSessionCompletedEvent($session));

    Queue::assertPushed(CallWebhookJob::class, 2);
});

test('the webhook jobs submits response data to webhook url', function () {
    Http::fake();

    $form = Form::factory()
        ->has(FormIntegration::factory([
            'webhook_method' => 'GET',
            'webhook_url' => 'https://void.work/submit'
        ]))->create();

    $session = FormSession::factory()->for($form)
        ->has(FormSessionResponse::factory([
            'value' => 'test response'
        ]))
        ->completed()
        ->create();

    with(new FormSubmitWebhookListener)
        ->handle(new FormSessionCompletedEvent($session));

    Http::assertSent(function ($request) use ($form) {
        $integration = $form->formIntegrations[0];

        return $request->url() === $integration->webhook_url
            && $request->method() === strtoupper($integration->webhook_method);
    });
});
