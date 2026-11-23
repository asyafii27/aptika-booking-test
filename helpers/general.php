<?php

function setPaginationOrNone($query, $inputs)
{
    $paginate = 10;

    if (!empty($inputs['page'])) {
        $pageSize = !empty($inputs['page_size']) ? $inputs['page_size'] : $paginate;
        $results = $query->paginate($pageSize);
    } else {
        $results = $query->get();
    }

    return $results;
}


function generalSortData($query, $inputs)
{
    if (!empty($inputs['sort_by']) && (!empty($inputs['sort_dir']))) {
        $sortDir = strtolower($inputs['sort_dir']);
        $sortDir = in_array($sortDir, ['asc', 'desc']) ? $sortDir : 'asc';

        $query->orderBy($inputs['sort_by'], $sortDir);
    }

    return $query;
}


function recapErrorLog($message, Throwable $e)
{
    return $message . ': ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine();
}


function recapErrorResponse($message, Throwable $e)
{
    if (config('app.debug')) {
        return $message . $e->getMessage();
    }

    return rtrim($message, ': ');
}
