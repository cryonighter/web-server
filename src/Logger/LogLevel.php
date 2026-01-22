<?php

namespace Logger;

enum LogLevel: int
{
    case FATAL = 1;
    case ERROR = 2;
    case WARNING = 3;
    case NOTICE = 4;
    case INFO = 5;
    case DEBUG = 6;
    case TRACE = 7;
}
