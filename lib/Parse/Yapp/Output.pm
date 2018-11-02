#
# Module Parse::Yapp::Output
#
# Copyright © 1998, 1999, 2000, 2001, Francois Desarmenien.
# Copyright © 2017 William N. Braswell, Jr.
# (see the pod text in Parse::Yapp module for use and distribution rights)
#
package Parse::Yapp::Output;
@ISA=qw ( Parse::Yapp::Lalr );

require 5.004;

use Parse::Yapp::Lalr;
use Parse::Yapp::Driver;

use strict;

use Carp;

sub _CopyDriver {
    my ($srcfile) = $Parse::Yapp::Driver::FILENAME;

    $srcfile =~ s/[.]pm$/.php/;

    open my $fp, '<', $srcfile or die "BUG: could not open $srcfile";
    my $source = do {
        local $/ = undef;
        <$fp>;
    };
    close $fp;

    ($source) = split /^__halt_compiler[(][)]/m, $source;

    return $source;
}

sub Output {
    my($self)=shift;

    $self->Options(@_);

    my($package)=$self->Option('classname');
    my($head,$states,$rules,$tail,$driver);
	my($namespace)=$self->Option('namespace');
    my($version)=$Parse::Yapp::Driver::VERSION;
    my($driverclass);
    my($text)=$self->Option('template') ||<<'EOT';
<?php
/*******************************************************************
*
*    This file was generated using Parse::Yapphp version <<$version>>.
*
*        Don't edit this file, use source file instead.
*
*             ANY CHANGES MADE HERE WILL BE LOST !
*
*******************************************************************/
<<$namespace>>class <<$package>> extends <<$driverclass>>
{
<<$head>>
    /**
     * <<$package>> constructor
     *
     * @param LexerInterface $lexer
     */
    public function __construct(LexerInterface $lexer)
    {
        parent::__construct($lexer);

        $this->VERSION = '<<$version>>';
        $this->STATES = <<$states>>;
        $this->RULES = <<$rules>>;
    }
<<$tail>>
}
EOT

    if (length $namespace) {
        $namespace = "namespace $namespace;\n\n";
    }

    $driverclass = "${package}Driver";

	$head= $self->Head();
	$rules=$self->RulesTable();
	$states=$self->DfaTable();
	$tail= $self->Tail();

	$driver=_CopyDriver();

	$text =~ s/<<(\$[a-z_][a-z_\d]*)>>/$1/igee;
    $driver =~ s{/[*]<<(\$[a-z_][a-z_\d]*)>>[*]/}{$1}igee;
    $driver =~ s{^abstract class Driver\b}{abstract class $driverclass}m;
die $driver;
	return ($text, $driver);
}

1;
