<?php

namespace SilverStripe\ContentReview\Tests\Behat\Context;

use SilverStripe\BehatExtension\Context\SilverStripeContext;

class FeatureContext extends SilverStripeContext
{
    /**
     * @Given /^the "([^"]*)" select element should(| not) have an option with (a|an) "([^"]*)" label$/
     * @param string $id
     * @param string $should
     * @param string $label
     */
    public function theSelectElementShouldHaveAnOptionWithALabel($id, $should, $label)
    {
        $n = $should === '' ? 1 : 0;
        $js = <<<JS
            ;let hasLabel = 0;
            document.querySelectorAll('#{$id} > option').forEach(function(option) {
                if (option.innerHTML == '$label') {
                    hasLabel = 1;
                }
            });
            return hasLabel;
JS;
        return $this->getSession()->evaluateScript($js) == $n;
    }
}
