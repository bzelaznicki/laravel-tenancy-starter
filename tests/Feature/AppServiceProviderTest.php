<?php

use Illuminate\Foundation\DevCommands;

it('registers the mailpit dev command with yellow styling', function (): void {
    $mailpit = collect(DevCommands::commands())->firstWhere('name', 'mailpit');

    // #fcd34d is the hex applied by DevCommand::yellow().
    expect($mailpit)->not->toBeNull()
        ->and($mailpit['command'])->toBe('mailpit')
        ->and($mailpit['color'])->toBe('#fcd34d');
});
