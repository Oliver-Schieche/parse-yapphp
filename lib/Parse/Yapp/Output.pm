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
	my($text)='#Included Parse/Yapp/Driver.pm file'.('-' x 40)."\n";
		open(DRV,$Parse::Yapp::Driver::FILENAME)
	or	die "BUG: could not open $Parse::Yapp::Driver::FILENAME";
	$text.="{\n".join('',<DRV>)."}\n";
	close(DRV);
	$text.='#End of include'.('-' x 50)."\n";
}

sub Output {
    my($self)=shift;

    $self->Options(@_);

    my($package)=$self->Option('classname');
    my($head,$states,$rules,$tail,$driver);
	my($namespace)=$self->Option('namespace');
    my($version)=$Parse::Yapp::Driver::VERSION;
    my($datapos);
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
namespace <<$namespace>>;

class <<$package>>
{
    /** @var string */
	protected $yyversion;
    /** @var array */
	protected $yystates;
    /** @var array */
	protected $yyrules;

<<$head>>

    public function __construct()
    {
        $this->yyversion = '<<$version>>';
        $this->yystates = <<$states>>;
        $this->yyrules = <<$rules>>;
    }

<<$tail>>

}
EOT

	$driver='use Parse::Yapp::Driver;';
        defined($package)
    or $package='Parse::Yapp::Default';

	$head= $self->Head();
	$rules=$self->RulesTable();
	$states=$self->DfaTable();
	$tail= $self->Tail();

		$self->Option('standalone')
	and	$driver=_CopyDriver();

	$text=~s/<<(\$[a-z_][a-z_\d]*)>>/$1/igee;
	$text;
}

1;
