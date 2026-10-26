<?php

it('focuses the workspace address input on load', function (): void {
    $page = visit('/');

    $page->navigate(browserUrl($page, config('app.domain'), '/login'));

    $page
        ->assertSee('Workspace address')
        ->assertScript('document.activeElement?.id', 'subdomain')
        ->assertNoJavaScriptErrors();
});
