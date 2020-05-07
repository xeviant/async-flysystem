<?php


namespace Xeviant\AsyncFlysystem\Adapter;


use League\Flysystem\Adapter\AbstractAdapter as AbstractLeagueAdapter;
use Xeviant\AsyncFlysystem\AdapterAsyncReadInterface;
use Xeviant\AsyncFlysystem\AsyncReadInterface;

abstract class AbstractAdapter extends AbstractLeagueAdapter implements AsyncReadInterface
{
}