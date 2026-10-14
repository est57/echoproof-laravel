<?php

declare(strict_types=1);

namespace Echoproof\Laravel\Http\Controllers;

use Echoproof\Laravel\Replay\FixtureStore;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class ReplayController
{
    public function __construct(private readonly FixtureStore $store, private readonly Repository $config)
    {
    }

    public function fixtures(Request $request, string $id): JsonResponse
    {
        if ($denied = $this->deny($request, $id)) {
            return $denied;
        }
        if (strlen($request->getContent()) > (int) $this->config->get('echoproof.max_body_bytes', PHP_INT_MAX)) {
            return new JsonResponse(['error' => 'fixtures too large'], 413);
        }
        $outbound = $request->json('outbound');
        if (! is_array($outbound) || ! array_is_list($outbound)) {
            return new JsonResponse(['error' => 'expected {"outbound": [...]}'], 422);
        }
        $this->store->putFixtures($id, $outbound);

        return new JsonResponse(['stored' => count($outbound)]);
    }

    public function outbound(Request $request, string $id): JsonResponse
    {
        if ($denied = $this->deny($request, $id)) {
            return $denied;
        }

        return new JsonResponse(['outbound' => $this->store->takeCaptured($id)]);
    }

    private function deny(Request $request, string $id): ?JsonResponse
    {
        if (! FixtureStore::validId($id)) {
            return new JsonResponse(['error' => 'invalid exchange id'], 422);
        }
        $token = (string) $this->config->get('echoproof.replay_token', '');
        if ($token !== '' && ! hash_equals($token, (string) $request->headers->get('x-echoproof-token', ''))) {
            return new JsonResponse(['error' => 'invalid replay token'], 403);
        }

        return null;
    }
}
