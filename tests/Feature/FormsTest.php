<?php

use Ppcharlier\StatamicEditorApi\Tests\Support\BuildsEntryFixtures;
use Statamic\Facades\Blueprint;
use Statamic\Facades\Form;

uses(BuildsEntryFixtures::class);

beforeEach(function () {
    Blueprint::make('contact')
        ->setNamespace('forms')
        ->setContents(['tabs' => ['main' => ['sections' => [['fields' => [
            ['handle' => 'name', 'field' => ['type' => 'text']],
            ['handle' => 'message', 'field' => ['type' => 'textarea']],
        ]]]]]])
        ->save();

    $this->form = tap(Form::make('contact')->title('Contact'))->save();

    $s1 = $this->form->makeSubmission()->data(['name' => 'Alice', 'message' => 'Bonjour']);
    $s1->save();
    $this->travel(1)->minutes();
    $s2 = $this->form->makeSubmission()->data(['name' => 'Bob', 'message' => 'Salut']);
    $s2->save();
    $this->submissionId = (string) $s1->id();

    $this->token = $this->makeSuperToken();
});

it('lists forms', function () {
    $this->withToken($this->token)
        ->getJson('/api/editor/v1/forms')
        ->assertOk()
        ->assertJsonPath('data.0.handle', 'contact');
});

it('filters the forms list for a non-super token to only permitted forms', function () {
    Blueprint::make('newsletter')
        ->setNamespace('forms')
        ->setContents(['tabs' => ['main' => ['sections' => [['fields' => [
            ['handle' => 'email', 'field' => ['type' => 'text']],
        ]]]]]])
        ->save();
    Form::make('newsletter')->title('Newsletter')->save();

    $token = $this->makeTokenWithPermissions(['view contact form submissions']);

    $response = $this->withToken($token)
        ->getJson('/api/editor/v1/forms')
        ->assertOk();

    expect(collect($response->json('data'))->pluck('handle')->all())->toBe(['contact']);
});

it('lists submissions newest first with pagination', function () {
    $response = $this->withToken($this->token)
        ->getJson('/api/editor/v1/forms/contact/submissions')
        ->assertOk();

    expect($response->json('meta.total'))->toBe(2)
        ->and($response->json('data.0.data.name'))->toBe('Bob')
        ->and($response->json('data.1.data.name'))->toBe('Alice')
        ->and($response->json('data.0'))->toHaveKeys(['id', 'date', 'data']);
});

it('deletes a submission', function () {
    $this->withToken($this->token)
        ->deleteJson('/api/editor/v1/forms/contact/submissions/'.$this->submissionId)
        ->assertStatus(204);

    expect($this->form->submission($this->submissionId))->toBeNull();
});

it('404s an unknown submission id', function () {
    $this->withToken($this->token)
        ->deleteJson('/api/editor/v1/forms/contact/submissions/999999.123')
        ->assertStatus(404);
});

it('enforces permissions and whitelist', function () {
    $viewOnly = $this->makeTokenWithPermissions(['view contact form submissions']);

    $this->withToken($viewOnly)->getJson('/api/editor/v1/forms/contact/submissions')->assertOk();
    $this->withToken($viewOnly)
        ->deleteJson('/api/editor/v1/forms/contact/submissions/'.$this->submissionId)
        ->assertStatus(403);

    config()->set('statamic.editor-api.resources.forms', ['autre']);
    $this->withToken($this->token)->getJson('/api/editor/v1/forms/contact/submissions')->assertStatus(404);
});
