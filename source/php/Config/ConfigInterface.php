<?php

namespace S3LocalIndex\Config;

interface ConfigInterface
{
  public function isEnabled(): bool;
  public function getCliPriority(): int;
  public function getPluginPriority(): int;
}