<?php


namespace SilverStripe\SearchService\Interfaces;


interface DependencyTracker
{
    public function getDependentDocuments(): iterable;
}
