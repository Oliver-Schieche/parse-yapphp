<?php
/* Module Parse::Yapphp::Driver
 *
 * This module is part of the Parse::Yapphp package available on your
 * nearest CPAN
 *
 * Any use of this module in a standalone parser make the included
 * text under the same copyright as the Parse::Yapphp module itself.
 *
 * This notice should remain unchanged.
 *
 * Copyright © 1998, 1999, 2000, 2001, Francois Desarmenien.
 * Copyright © 2017 William N. Braswell, Jr.
 * (see the pod text in Parse::Yapphp module for use and distribution rights)
 */
//------------------------------------------------------------------------------
/*<<$namespace>>*/abstract class Driver
{
    /** @var array */
    protected $RULES;
    /** @var array */
    protected $STATES;
    /** @var string */
    protected $VERSION = '/*<<$version>>*/';

    /** @var mixed */
    protected $CHECK, $DEBUG, $DOTPOS, $ERRST, $NBERR, $STACK, $TOKEN, $VALUE;

    /**
     * @var LexerInterface
     */
    protected $lexer;

    /**
     * @return array
     */
    abstract protected function getRules(): array;

    /**
     * @return array
     */
    abstract protected function getStates(): array;

    /**
     * Driver constructor.
     *
     * @param LexerInterface $lexer
     */
    public function __construct(LexerInterface $lexer)
    {
        $this->setLexer($lexer);
        $this->DEBUG = 0;
        $this->RULES = $this->getRules();
        $this->STATES = $this->getStates();
    }

    /**
     * @return LexerInterface
     */
    public function getLexer(): LexerInterface
    {
        return $this->lexer;
    }

    /**
     * @param LexerInterface $lexer
     * @return Driver
     */
    public function setLexer(LexerInterface $lexer): self
    {
        $this->lexer = $lexer;
        return $this;
    }

    /**
     * @return null
     */
    public function YYAbort()
    {
        $this->CHECK = 'ABORT';
        return null;
    }

    /**
     * @return null
     */
    public function YYAccept()
    {
        $this->CHECK = 'ACCEPT';
        return null;
    }

    /**
     * @param int|null $debug
     * @return int
     */
    public function YYDebug(int $debug = null)
    {
        if (null !== $debug) {
            $this->DEBUG = $debug;
        }

        return $this->DEBUG;
    }

    /**
     * @return null
     */
    public function YYErrok()
    {
        $this->ERRST = 0;
        return null;
    }

    /**
     * @return null
     */
    public function YYError()
    {
        $this->CHECK = 'ERROR';
        return null;
    }

    /**
     * @return bool
     */
    public function YYNberr()
    {
        return $this->NBERR;
    }

    /**
     * @return bool
     */
    public function YYRecovering(): bool
    {
        return 0 !== $this->ERRST;
    }

    /**
     * @return mixed
     */
    public function YYParse()
    {
        return $this->parse();
    }

    /**
     * @param int $flag
     * @param string $message
     * @param mixed ...$arguments
     * @return Driver
     */
    protected function debug(int $flag, string $message, ...$arguments): self
    {
        if (0 !== ($this->DEBUG & $flag)) {
            $output = \vsprintf($message, $arguments);
            $this->debugOutput($flag, $output);
        }

        return $this;
    }

    /**
     * @param int $flag
     * @param string $output
     */
    protected function debugOutput(int $flag, string $output)
    {
        \fwrite(\STDERR, $output);
    }

    /**
     * @return mixed
     */
    protected function parse()
    {
        $rules = $this->RULES;
        $states = $this->STATES;
        $lexer = $this->getLexer();
        $dbgerror = 0;

        $this->ERRST = 0;
        $this->NBERR = 0;
        $this->TOKEN = null;
        $this->VALUE = null;
        $this->STACK = [[0, null]];
        $this->CHECK = '';

        while (true) {
            $stateno = $this->STACK[\count($this->STACK) - 1][0];
            $actions = $states[$stateno];
            $act = $actions['DEFAULT'] ?? null;

            $this->debug(2, "In state %d:\n", $stateno)
                ->debug(8, "Stack:[%s]\n", \implode(',', \array_map(function($s) {
                    return $s[0];
                }, $this->STACK)));

            if (\array_key_exists('ACTIONS', $actions)) {
                if (null === $this->TOKEN) {
                    list($this->TOKEN, $this->VALUE) = $lexer->lex($this);
                    $this->debug(1, "Needed token; got >%s<.\n", $this->TOKEN);
                }

                if (\array_key_exists($this->TOKEN, $actions['ACTIONS'])) {
                    $act = $actions['ACTIONS'][$this->TOKEN];
                }
            }

            if (null !== $act) {
                if ($act > 0) { // Shift
                    $this->debug(4, "Shift and go to state %d.\n", $act);

                    if ($this->ERRST && 0 === --$this->ERRST && $dbgerror) {
                        $this->debug(16, "**End of error recovery.\n");
                        $dbgerror = 0;
                    }

                    $this->STACK[] = [$act, $this->VALUE];

                    if ('' !== $this->TOKEN) {
                        $this->TOKEN = $this->VALUE = null;
                    }
                    continue;
                }

                // Reduce
                list($lhs,$len,$code) = $rules[-$act];

                if ($act) {
                    $this->debug(4, 'Reduce using rule %d (%d,%d): ', -$act, $lhs, $len);
                } else {
                    $this->YYAccept();
                }

                $this->DOTPOS = $len;

                if ('@' === $lhs[0]) { // In-line rule
                    if (!\preg_match('~^@\d+-(\d+)$', $lhs, $match)) {
                        throw new \RuntimeException("In-line rule '$lhs' ill-formed; report this as a BUG.");
                    }

                    $this->DOTPOS = (int) $match[1];
                }

                if (!$this->DOTPOS) {
                    $sempar = [];
                } else {
                    $sempar = \array_map(function($s) {
                        return $s[1];
                    }, \array_slice($this->STACK, -$this->DOTPOS));
                }

                if (null === $code) {
                    $semval = $sempar[0] ?? null;
                } else {
                    \array_unshift($sempar, null);
                    $semval = $code($sempar);
                }

                \array_splice($this->STACK, -$len, $len);

                if ('ACCEPT' === $this->CHECK) {
                    $this->debug(4, "Accept.\n");
                    return $semval;
                }

                if ('ABORT' === $this->CHECK) {
                    $this->debug(4, "Abort.\n");
                    return null;
                }

                $stackTop = $this->STACK[\count($this->STACK) - 1];
                $this->debug(4, 'Back to state %d, then ', $stackTop[0]);

                if ('ERROR' !== $this->CHECK) {
                    $this->debug(4, "go to state %d.\n", $states[$stackTop[0]]['GOTOS'][$lhs]);

                    if ($dbgerror && 0 === $this->ERRST) {
                        $this->debug(16, "**End of error recovery.\n");
                        $dbgerror = 0;
                    }

                    $this->STACK[] = [$states[$stackTop[0]]['GOTOS'][$lhs], $semval];
                    $this->CHECK = '';
                    continue;
                }

                $this->debug(4, "Forced error recovery.\n");
                $this->CHECK = '';
            }

            if (!$this->ERRST) {
                $this->ERRST = 1;
                $this->_Error();

                if (!$this->ERRST) { // if 0, then YYErrok has been called
                    continue;        // so continue parsing
                }

                $this->debug(16, "**Entering error recovery.\n");
                ++$dbgerror;
                ++$this->NBERR;
            }

            if (3 === $this->ERRST) { // The next token is invalid: discard it
                if ('' === $this->TOKEN) { // End of input... No hope
                    $this->debug(16, "**At EOF: aborting.\n");
                    return null;
                }

                $this->debug(16, "**Discard invalid token >%s<.\n", $this->TOKEN);
                $this->TOKEN = $this->VALUE = null;
            }

            $this->ERRST = 3;

            while (\count($this->STACK)) {
                $stackTop = $this->STACK[\count($this->STACK) - 1];

                if (!\array_key_exists('ACTIONS', $states[$stackTop[0]]) ||
                    !\array_key_exists('error', $states[$stackTop[0]]['ACTIONS']) ||
                    $states[$stackTop[0]]['ACTIONS']['error'] <= 0) {
                    $this->debug(16, "**Pop state %d.\n", $stackTop[0]);
                    \array_pop($this->STACK);
                } else {
                    break;
                }
            }

            if (0 === \count($this->STACK)) {
                $this->debug(16, "**No state left on stack: aborting.\n");
                return null;
            }

            // Shift the error token
            $stackTop = $this->STACK[\count($this->STACK) - 1];
            $this->debug(16, "**Shift \$error token and go to state %d.\n", $states[$stackTop[0]]['ACTIONS']['error']);
            $this->STACK[] = [$states[$stackTop[0]]['ACTIONS']['error'], null];
        }
    }

    /**
     * ...
     */
    protected function _Error()
    {
        print "Parse error.\n";
    }
}
__halt_compiler();

sub YYParse {
    my($self)=shift;
    my($retval);

	_CheckParams( \@params, \%params, \@_, $self );

	if($$self{DEBUG}) {
		_DBLoad();
		$retval = eval '$self->_DBParse()';#Do not create stab entry on compile
        $@ and die $@;
	}
	else {
		$retval = $self->_Parse();
	}
    $retval
}

sub YYData {
	my($self)=shift;

		exists($$self{USER})
	or	$$self{USER}={};

	$$self{USER};
	
}

sub YYErrok {
	my($self)=shift;

	${$$self{ERRST}}=0;
    undef;
}

sub YYNberr {
	my($self)=shift;

	${$$self{NBERR}};
}

sub YYRecovering {
	my($self)=shift;

	${$$self{ERRST}} != 0;
}

sub YYAbort {
	my($self)=shift;

	${$$self{CHECK}}='ABORT';
    undef;
}

sub YYAccept {
	my($self)=shift;

	${$$self{CHECK}}='ACCEPT';
    undef;
}

sub YYError {
	my($self)=shift;

	${$$self{CHECK}}='ERROR';
    undef;
}

sub YYSemval {
	my($self)=shift;
	my($index)= $_[0] - ${$$self{DOTPOS}} - 1;

		$index < 0
	and	-$index <= @{$$self{STACK}}
	and	return $$self{STACK}[$index][1];

	undef;	#Invalid index
}

sub YYCurtok {
	my($self)=shift;

        @_
    and ${$$self{TOKEN}}=$_[0];
    ${$$self{TOKEN}};
}

sub YYCurval {
	my($self)=shift;

        @_
    and ${$$self{VALUE}}=$_[0];
    ${$$self{VALUE}};
}

sub YYExpect {
    my($self)=shift;

    keys %{$self->{STATES}[$self->{STACK}[-1][0]]{ACTIONS}}
}

sub YYLexer {
    my($self)=shift;

	$$self{LEX};
}


#################
# Private stuff #
#################


sub _CheckParams {
	my($mandatory,$checklist,$inarray,$outhash)=@_;
	my($prm,$value);
	my($prmlst)={};

	while(($prm,$value)=splice(@$inarray,0,2)) {
        $prm=uc($prm);
			exists($$checklist{$prm})
		or	croak("Unknow parameter '$prm'");
			ref($value) eq $$checklist{$prm}
		or	croak("Invalid value for parameter '$prm'");
        $prm=unpack('@2A*',$prm);
		$$outhash{$prm}=$value;
	}
	for (@$mandatory) {
			exists($$outhash{$_})
		or	croak("Missing mandatory parameter '".lc($_)."'");
	}
}

sub _Error {
	print "Parse error.\n";
}

sub _DBLoad {
	{
		no strict 'refs';

			exists(${__PACKAGE__.'::'}{_DBParse})#Already loaded ?
		and	return;
	}
	my($fname)=__FILE__;
	my(@drv);
	open(DRV,"<$fname") or die "Report this as a BUG: Cannot open $fname";
	while(<DRV>) {
                	/^\s*sub\s+_Parse\s*{\s*$/ .. /^\s*}\s*#\s*_Parse\s*$/
        	and     do {
                	s/^#DBG>//;
                	push(@drv,$_);
        	}
	}
	close(DRV);

	$drv[0]=~s/_P/_DBP/;
	eval join('',@drv);
}

#Note that for loading debugging version of the driver,
#this file will be parsed from 'sub _Parse' up to '}#_Parse' inclusive.
#So, DO NOT remove comment at end of sub !!!
sub _Parse {
    my($self)=shift;

	my($rules,$states,$lex,$error)
     = @$self{ 'RULES', 'STATES', 'LEX', 'ERROR' };
	my($errstatus,$nberror,$token,$value,$stack,$check,$dotpos)
     = @$self{ 'ERRST', 'NBERR', 'TOKEN', 'VALUE', 'STACK', 'CHECK', 'DOTPOS' };

#DBG>	my($debug)=$$self{DEBUG};
#DBG>	my($dbgerror)=0;

#DBG>	my($ShowCurToken) = sub {
#DBG>		my($tok)='>';
#DBG>		for (split('',$$token)) {
#DBG>			$tok.=		(ord($_) < 32 or ord($_) > 126)
#DBG>					?	sprintf('<%02X>',ord($_))
#DBG>					:	$_;
#DBG>		}
#DBG>		$tok.='<';
#DBG>	};

	$$errstatus=0;
	$$nberror=0;
	($$token,$$value)=(undef,undef);
	@$stack=( [ 0, undef ] );
	$$check='';

    while(1) {
        my($actions,$act,$stateno);

        $stateno=$$stack[-1][0];
        $actions=$$states[$stateno];

#DBG>	print STDERR ('-' x 40),"\n";
#DBG>		$debug & 0x2
#DBG>	and	print STDERR "In state $stateno:\n";
#DBG>		$debug & 0x08
#DBG>	and	print STDERR "Stack:[".
#DBG>					 join(',',map { $$_[0] } @$stack).
#DBG>					 "]\n";


        if  (exists($$actions{ACTIONS})) {

				defined($$token)
            or	do {
				($$token,$$value)=&$lex($self);
#DBG>				$debug & 0x01
#DBG>			and	print STDERR "Need token. Got ".&$ShowCurToken."\n";
			};

            $act=   exists($$actions{ACTIONS}{$$token})
                    ?   $$actions{ACTIONS}{$$token}
                    :   exists($$actions{DEFAULT})
                        ?   $$actions{DEFAULT}
                        :   undef;
        }
        else {
            $act=$$actions{DEFAULT};
#DBG>			$debug & 0x01
#DBG>		and	print STDERR "Don't need token.\n";
        }

            defined($act)
        and do {

                $act > 0
            and do {        #shift

#DBG>				$debug & 0x04
#DBG>			and	print STDERR "Shift and go to state $act.\n";

					$$errstatus
				and	do {
					--$$errstatus;

#DBG>					$debug & 0x10
#DBG>				and	$dbgerror
#DBG>				and	$$errstatus == 0
#DBG>				and	do {
#DBG>					print STDERR "**End of Error recovery.\n";
#DBG>					$dbgerror=0;
#DBG>				};
				};


                push(@$stack,[ $act, $$value ]);

					$$token ne ''	#Don't eat the eof
				and	$$token=$$value=undef;
                next;
            };

            #reduce
            my($lhs,$len,$code,@sempar,$semval);
            ($lhs,$len,$code)=@{$$rules[-$act]};

#DBG>			$debug & 0x04
#DBG>		and	$act
#DBG>		and	print STDERR "Reduce using rule ".-$act." ($lhs,$len): ";

                $act
            or  $self->YYAccept();

            $$dotpos=$len;

                unpack('A1',$lhs) eq '@'    #In line rule
            and do {
                    $lhs =~ /^\@[0-9]+\-([0-9]+)$/
                or  die "In line rule name '$lhs' ill formed: ".
                        "report it as a BUG.\n";
                $$dotpos = $1;
            };

            @sempar =       $$dotpos
                        ?   map { $$_[1] } @$stack[ -$$dotpos .. -1 ]
                        :   ();

            $semval = $code ? &$code( $self, @sempar )
                            : @sempar ? $sempar[0] : undef;

            splice(@$stack,-$len,$len);

                $$check eq 'ACCEPT'
            and do {

#DBG>			$debug & 0x04
#DBG>		and	print STDERR "Accept.\n";

				return($semval);
			};

                $$check eq 'ABORT'
            and	do {

#DBG>			$debug & 0x04
#DBG>		and	print STDERR "Abort.\n";

				return(undef);

			};

#DBG>			$debug & 0x04
#DBG>		and	print STDERR "Back to state $$stack[-1][0], then ";

                $$check eq 'ERROR'
            or  do {
#DBG>				$debug & 0x04
#DBG>			and	print STDERR 
#DBG>				    "go to state $$states[$$stack[-1][0]]{GOTOS}{$lhs}.\n";

#DBG>				$debug & 0x10
#DBG>			and	$dbgerror
#DBG>			and	$$errstatus == 0
#DBG>			and	do {
#DBG>				print STDERR "**End of Error recovery.\n";
#DBG>				$dbgerror=0;
#DBG>			};

			    push(@$stack,
                     [ $$states[$$stack[-1][0]]{GOTOS}{$lhs}, $semval ]);
                $$check='';
                next;
            };

#DBG>			$debug & 0x04
#DBG>		and	print STDERR "Forced Error recovery.\n";

            $$check='';

        };

        #Error
            $$errstatus
        or   do {

            $$errstatus = 1;
            &$error($self);
                $$errstatus # if 0, then YYErrok has been called
            or  next;       # so continue parsing

#DBG>			$debug & 0x10
#DBG>		and	do {
#DBG>			print STDERR "**Entering Error recovery.\n";
#DBG>			++$dbgerror;
#DBG>		};

            ++$$nberror;

        };

			$$errstatus == 3	#The next token is not valid: discard it
		and	do {
				$$token eq ''	# End of input: no hope
			and	do {
#DBG>				$debug & 0x10
#DBG>			and	print STDERR "**At eof: aborting.\n";
				return(undef);
			};

#DBG>			$debug & 0x10
#DBG>		and	print STDERR "**Dicard invalid token ".&$ShowCurToken.".\n";

			$$token=$$value=undef;
		};

        $$errstatus=3;

		while(	  @$stack
			  and (		not exists($$states[$$stack[-1][0]]{ACTIONS})
			        or  not exists($$states[$$stack[-1][0]]{ACTIONS}{error})
					or	$$states[$$stack[-1][0]]{ACTIONS}{error} <= 0)) {

#DBG>			$debug & 0x10
#DBG>		and	print STDERR "**Pop state $$stack[-1][0].\n";

			pop(@$stack);
		}

			@$stack
		or	do {

#DBG>			$debug & 0x10
#DBG>		and	print STDERR "**No state left on stack: aborting.\n";

			return(undef);
		};

		#shift the error token

#DBG>			$debug & 0x10
#DBG>		and	print STDERR "**Shift \$error token and go to state ".
#DBG>						 $$states[$$stack[-1][0]]{ACTIONS}{error}.
#DBG>						 ".\n";

		push(@$stack, [ $$states[$$stack[-1][0]]{ACTIONS}{error}, undef ]);

    }

    #never reached
	croak("Error in driver logic. Please, report it as a BUG");

}#_Parse
#DO NOT remove comment

1;

