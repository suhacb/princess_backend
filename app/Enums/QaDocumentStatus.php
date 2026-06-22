<?php

namespace App\Enums;

enum QaDocumentStatus: string
{
    case Draft     = 'draft';
    case InReview  = 'in_review';
    case Confirmed = 'confirmed';
    case Superseded = 'superseded';
}
