<?php

namespace Xeviant\AsyncFlysystem\Adapter;

use League\Flysystem\Adapter\AbstractAdapter as AbstractLeagueAdapter;

abstract class AbstractAdapter extends AbstractLeagueAdapter implements AsyncAdapterInterface
{
}