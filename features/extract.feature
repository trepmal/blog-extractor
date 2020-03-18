Feature: Extract single site from multisite

  Scenario: Extract main site
    Given a WP multisite install
    When I run `wp extract 1`
    Then STDOUT should contain:
      """
      Success: archive-1.tar.gz created!
      """

    When I run `wp user list --field=user_login`
    Then STDOUT should be:
      """
      admin
      """

    When I run `wp option get home`
    Then STDOUT should be:
      """
      http://example.com
      """

  Scenario: Extract non-existent site
    Given a WP multisite install
    When I try `wp extract 2888`
    Then STDERR should be:
      """
      Error: Given blog id is invalid.
      """
    And the return code should be 1

  Scenario: Extract sub site
    Given a WP multisite install
    And I run `wp site create --slug=newsite --porcelain`
    And save STDOUT as {SITE_ID}
    And I run `wp option get home`
    And save STDOUT as {HOME_URL}
    And I run `wp user create bobjones bob@example.com --role=author --url={HOME_URL}/newsite`

    When I run `wp extract {SITE_ID}`
    Then STDOUT should contain:
      """
      Success: archive-{SITE_ID}.tar.gz created!
      """

    When I run `wp user list --field=user_login --url={HOME_URL}/newsite`
    Then STDOUT should be:
      """
      admin
      bobjones
      """

    When I run `wp option get home --url={HOME_URL}/newsite`
    Then STDOUT should be:
      """
      http://example.com/newsite
      """
