<?php

declare(strict_types=1);

use enzolarosa\MqttBroadcast\Support\ProcessIdentifier;

describe('ProcessIdentifier', function () {
    describe('pid()', function () {
        it('returns the current process id', function () {
            $pid = ProcessIdentifier::pid();

            expect($pid)
                ->toBeInt()
                ->toBeGreaterThan(0)
                ->toBe(getmypid());
        });

        it('returns consistent pid across multiple calls', function () {
            $pid1 = ProcessIdentifier::pid();
            $pid2 = ProcessIdentifier::pid();

            expect($pid1)->toBe($pid2);
        });
    });

    describe('hostname()', function () {
        it('returns a slugified hostname', function () {
            $hostname = ProcessIdentifier::hostname();

            expect($hostname)
                ->toBeString()
                ->not->toBeEmpty();
        });

        it('returns the same hostname across multiple calls', function () {
            $hostname1 = ProcessIdentifier::hostname();
            $hostname2 = ProcessIdentifier::hostname();

            expect($hostname1)->toBe($hostname2);
        });

        it('returns a valid slug format', function () {
            $hostname = ProcessIdentifier::hostname();

            // Should only contain lowercase letters, numbers, and hyphens
            expect($hostname)->toMatch('/^[a-z0-9-]+$/');
        });

        it('has no uppercase letters or special characters', function () {
            $hostname = ProcessIdentifier::hostname();

            expect($hostname)
                ->not->toMatch('/[A-Z]/')
                ->not->toMatch('/[^a-z0-9-]/');
        });
    });

    describe('generateName()', function () {
        it('generates a unique name without prefix', function () {
            $name = ProcessIdentifier::generateName();

            expect($name)
                ->toBeString()
                ->not->toBeEmpty()
                ->toContain('-'); // Should contain hostname-token format
        });

        it('generates a name with hostname component', function () {
            $name = ProcessIdentifier::generateName();
            $hostname = ProcessIdentifier::hostname();

            expect($name)->toStartWith($hostname);
        });

        it('generates a name with custom prefix', function () {
            $name = ProcessIdentifier::generateName('worker');

            expect($name)
                ->toBeString()
                ->toStartWith('worker-');
        });

        it('generates a name with prefix and hostname', function () {
            $name = ProcessIdentifier::generateName('broker');
            $hostname = ProcessIdentifier::hostname();

            expect($name)
                ->toStartWith('broker-')
                ->toContain($hostname);
        });

        it('returns consistent token across multiple calls without prefix', function () {
            $name1 = ProcessIdentifier::generateName();
            $name2 = ProcessIdentifier::generateName();

            // Should be identical because static token
            expect($name1)->toBe($name2);
        });

        it('returns consistent token across multiple calls with same prefix', function () {
            $name1 = ProcessIdentifier::generateName('worker');
            $name2 = ProcessIdentifier::generateName('worker');

            // Should be identical because static token
            expect($name1)->toBe($name2);
        });

        it('generates different names for different prefixes but same token', function () {
            $name1 = ProcessIdentifier::generateName('worker');
            $name2 = ProcessIdentifier::generateName('broker');

            expect($name1)
                ->not->toBe($name2)
                ->and($name1)->toContain(ProcessIdentifier::hostname())
                ->and($name2)->toContain(ProcessIdentifier::hostname());

            // Extract tokens (last part after last hyphen)
            $parts1 = explode('-', $name1);
            $parts2 = explode('-', $name2);
            $token1 = end($parts1);
            $token2 = end($parts2);

            // Tokens should be the same (static)
            expect($token1)->toBe($token2);
        });

        it('has valid format: prefix-hostname-token or hostname-token', function () {
            $nameWithoutPrefix = ProcessIdentifier::generateName();
            $nameWithPrefix = ProcessIdentifier::generateName('test');

            // Without prefix: hostname-token (at least 2 parts)
            expect(substr_count($nameWithoutPrefix, '-'))->toBeGreaterThanOrEqual(1);

            // With prefix: prefix-hostname-token (at least 3 parts)
            expect(substr_count($nameWithPrefix, '-'))->toBeGreaterThanOrEqual(2);
        });

        it('handles empty string prefix as no prefix', function () {
            $nameWithEmpty = ProcessIdentifier::generateName('');
            $nameWithNull = ProcessIdentifier::generateName();

            // Should be identical
            expect($nameWithEmpty)->toBe($nameWithNull);
        });

        it('handles whitespace in prefix by slugifying', function () {
            $name = ProcessIdentifier::generateName('my worker');

            expect($name)->toStartWith('my-worker-');
        });
    });

    describe('integration', function () {
        it('generates unique identifiable names for different processes', function () {
            $pid = ProcessIdentifier::pid();
            $hostname = ProcessIdentifier::hostname();
            $brokerName = ProcessIdentifier::generateName('broker');

            expect($brokerName)
                ->toContain($hostname)
                ->toContain('broker')
                ->and($pid)->toBeInt();
        });

        it('provides all components needed for process identification', function () {
            $components = [
                'pid' => ProcessIdentifier::pid(),
                'hostname' => ProcessIdentifier::hostname(),
                'name' => ProcessIdentifier::generateName('test'),
            ];

            expect($components['pid'])->toBeInt();
            expect($components['hostname'])->toBeString();
            expect($components['name'])->toContain($components['hostname']);
        });
    });
});
