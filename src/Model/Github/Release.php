<?php

namespace Sonata\DevKit\Model\Github;

/**
 * @author Maximilian Berghoff <Maximilian.Berghoff@mayflower.de>
 */
class Release
{
    /**
     * @var
     */
    private $url;
    /**
     * @var
     */
    private $id;
    /***
     * @var
     */
    private $tagName;
    /**
     * @var
     */
    private $name;
    /**
     * @var string
     */
    private $preRelease;
    /**
     * @var bool
     */
    private $stable;

    private function __construct($properties)
    {
        foreach ($properties as $property => $value) {
            if (property_exists($this, $property)) {
                $this->{$property} = $value;
            }
        }
    }

    /**
     * @param $release
     *
     * @return Release
     */
    public static function fromArray($release)
    {
        $properties = [];
        self::assertKeysExist(['url', 'id', 'tag_name', 'name', 'prerelease'], $release);
        $properties['url'] = $release['url'];
        $properties['id'] = $release['id'];
        $properties['tagName'] = $release['tag_name'];
        $properties['name'] = $release['name'];
        $properties['preRelease'] = $release['prerelease'];
        $properties['stable'] = !(bool)$release['prerelease'];

        return new self($properties);
    }

    private static function assertKeysExist($keys, $list)
    {
        foreach ($keys as $key) {
            self::assertKeyExists($key, $list);
        }
    }

    private static function assertKeyExists($key, $list)
    {
        if (!array_key_exists($key, $list)) {
            throw  new \InvalidArgumentException('The array key '.$key.' should exist. Got following only: '.implode(', ', array_keys($list)));
        }
    }

    /**
     * @return mixed
     */
    public function getUrl()
    {
        return $this->url;
    }

    /**
     * @return mixed
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @return mixed
     */
    public function getTagName()
    {
        return $this->tagName;
    }

    /**
     * @return mixed
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * @return string
     */
    public function getPreRelease()
    {
        return $this->preRelease;
    }

    /**
     * @return bool
     */
    public function isStable()
    {
        return $this->stable;
    }
}
