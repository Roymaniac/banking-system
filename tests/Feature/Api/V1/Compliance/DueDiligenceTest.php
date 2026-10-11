<?php

declare(strict_types=1);

use App\Models\User;
use Compliance\Application\DueDiligence\DueDiligenceGateway;
use Customer\Domain\Customer\ValueObject\CustomerId;
use Identity\Application\Authorization\AuthorizationChecker;
use Identity\Domain\Authorization\ValueObject\Permission;
use Identity\Domain\User\ValueObject\UserId;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

function dueDiligenceUser(string $email): User
{
    return User::factory()->create([
        'identity_user_id' => UserId::generate()->value(),
        'email' => $email,
        'email_verified_at' => now(),
    ]);
}

function createDueDiligenceCustomer(User $user): string
{
    Sanctum::actingAs($user);

    return (string) test()->postJson('/api/v1/customer/profile', [
        'first_name' => 'Compliance',
        'last_name' => 'Customer',
        'date_of_birth' => '1990-12-10',
    ])->assertCreated()->json('data.customer.id');
}

/** @return array{document_type: string, issuing_country: string, document_number: string} */
function dueDiligencePayload(string $number = 'A123456789'): array
{
    return [
        'document_type' => 'passport',
        'issuing_country' => 'NG',
        'document_number' => $number,
    ];
}

function allowDueDiligenceReview(): void
{
    app()->instance(AuthorizationChecker::class, new class implements AuthorizationChecker
    {
        public function allows(UserId $userId, Permission $permission): bool
        {
            return true;
        }
    });
}

it('stores identity evidence safely and returns only a masked document', function (): void {
    $customer = dueDiligenceUser('kyc-customer@example.com');
    createDueDiligenceCustomer($customer);
    Sanctum::actingAs($customer);

    $this->getJson('/api/v1/customer/compliance/due-diligence')
        ->assertOk()
        ->assertJsonPath('data.due_diligence', null);

    $this->postJson('/api/v1/customer/compliance/due-diligence', dueDiligencePayload())
        ->assertStatus(202)
        ->assertJsonPath('data.due_diligence.status', 'submitted')
        ->assertJsonPath('data.due_diligence.document_last_four', '6789')
        ->assertJsonMissingPath('data.due_diligence.document_number');

    $stored = DB::table('customer_due_diligence_cases')->first();
    expect($stored->document_number_encrypted)->not->toContain('A123456789')
        ->and($stored->document_fingerprint)->toHaveLength(64);
    $this->assertDatabaseHas('customer_due_diligence_events', ['action' => 'submitted']);
});

it('prevents one identity document from being linked to two customers', function (): void {
    $first = dueDiligenceUser('first-kyc@example.com');
    createDueDiligenceCustomer($first);
    Sanctum::actingAs($first);
    $this->postJson('/api/v1/customer/compliance/due-diligence', dueDiligencePayload())
        ->assertStatus(202);

    $second = dueDiligenceUser('second-kyc@example.com');
    createDueDiligenceCustomer($second);
    Sanctum::actingAs($second);
    $this->postJson('/api/v1/customer/compliance/due-diligence', dueDiligencePayload())
        ->assertConflict()
        ->assertJsonPath('message', 'This identity document is already linked to another customer.');
});

it('lets an independent privileged reviewer approve a submitted case', function (): void {
    $customer = dueDiligenceUser('approved-kyc@example.com');
    $customerId = createDueDiligenceCustomer($customer);
    Sanctum::actingAs($customer);
    $caseId = $this->postJson(
        '/api/v1/customer/compliance/due-diligence',
        dueDiligencePayload('B987654321'),
    )->assertStatus(202)->json('data.due_diligence.id');

    allowDueDiligenceReview();
    $reviewer = dueDiligenceUser('reviewer@example.com');
    Sanctum::actingAs($reviewer, ['privileged']);

    $this->getJson('/api/v1/compliance/due-diligence')
        ->assertOk()
        ->assertJsonCount(1, 'data.cases')
        ->assertJsonPath('data.cases.0.customer_id', $customerId);
    $this->postJson("/api/v1/compliance/due-diligence/{$caseId}/approve", [
        'risk_level' => 'standard',
    ])->assertOk()
        ->assertJsonPath('data.due_diligence.status', 'approved')
        ->assertJsonPath('data.due_diligence.risk_level', 'standard');

    $this->assertDatabaseHas('customer_due_diligence_events', [
        'case_id' => $caseId,
        'action' => 'approved',
        'actor_id' => $reviewer->identity_user_id,
    ]);
    expect(app(DueDiligenceGateway::class)->isApproved(
        new CustomerId($customerId),
    ))->toBeTrue();
});

it('prevents a customer from deciding their own due-diligence case', function (): void {
    $customer = dueDiligenceUser('self-review-kyc@example.com');
    createDueDiligenceCustomer($customer);
    Sanctum::actingAs($customer);
    $caseId = $this->postJson(
        '/api/v1/customer/compliance/due-diligence',
        dueDiligencePayload('SELF123456'),
    )->assertStatus(202)->json('data.due_diligence.id');

    allowDueDiligenceReview();
    Sanctum::actingAs($customer, ['privileged']);

    $this->postJson("/api/v1/compliance/due-diligence/{$caseId}/approve", [
        'risk_level' => 'low',
    ])->assertConflict()
        ->assertJsonPath('message', 'A reviewer cannot decide their own due-diligence case.');

    $this->assertDatabaseHas('customer_due_diligence_cases', [
        'id' => $caseId,
        'status' => 'submitted',
    ]);
});

it('records a rejection and allows corrected evidence to be resubmitted', function (): void {
    $customer = dueDiligenceUser('resubmit-kyc@example.com');
    createDueDiligenceCustomer($customer);
    Sanctum::actingAs($customer);
    $caseId = $this->postJson(
        '/api/v1/customer/compliance/due-diligence',
        dueDiligencePayload('C123456789'),
    )->assertStatus(202)->json('data.due_diligence.id');

    allowDueDiligenceReview();
    Sanctum::actingAs(dueDiligenceUser('rejector@example.com'), ['privileged']);
    $this->postJson("/api/v1/compliance/due-diligence/{$caseId}/reject", [
        'reason' => 'The supplied image could not be read clearly.',
    ])->assertOk()
        ->assertJsonPath('data.due_diligence.status', 'rejected');

    Sanctum::actingAs($customer);
    $this->postJson(
        '/api/v1/customer/compliance/due-diligence',
        dueDiligencePayload('D123456789'),
    )->assertStatus(202)
        ->assertJsonPath('data.due_diligence.id', $caseId)
        ->assertJsonPath('data.due_diligence.status', 'submitted');

    $this->assertDatabaseHas('customer_due_diligence_events', [
        'case_id' => $caseId,
        'action' => 'resubmitted',
    ]);
});
