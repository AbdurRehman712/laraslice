<?php

namespace LaraSlice\Core\Contracts;

use LaraSlice\Core\Base\PagedDataList;

interface IListingDataService
{
    /**
     * Retrieve a paginated and filtered list of items.
     *
     * @param IFilterObject $filter
     * @return PagedDataList
     */
    public function getList(IFilterObject $filter): PagedDataList;
}
