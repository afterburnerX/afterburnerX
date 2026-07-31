<?php

declare(strict_types=1);

use App\Mailer;

test('dotStuff() escapes lines that start with a dot per RFC 5321', function () {
    assertSame("..hidden\nnormal line\n..also hidden", Mailer::dotStuff(".hidden\nnormal line\n.also hidden"));
});

test('dotStuff() leaves lines with a dot elsewhere untouched', function () {
    assertSame('Sentence with a mid-line. Full stop.', Mailer::dotStuff('Sentence with a mid-line. Full stop.'));
});

test('dotStuff() leaves a body with no leading dots untouched', function () {
    $body = "Hi there,\n\nJust a normal message.\n\n- AfterburnerX";
    assertSame($body, Mailer::dotStuff($body));
});
