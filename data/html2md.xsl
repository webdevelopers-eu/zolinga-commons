<?xml version="1.0" encoding="UTF-8"?>
<!-- credit: https://gist.github.com/gabetax/1702774 (modified for XSLT 1.0) -->
<xsl:stylesheet xmlns:xsl="http://www.w3.org/1999/XSL/Transform"
    xmlns:functx="http://www.functx.com" version="1.0">

    <xsl:output method="text" />
    <xsl:strip-space elements="*" />

    <!-- Required for li indenting -->
    <!-- Implementation of repeat-string using XSLT 1.0 constructs -->
    <xsl:template name="repeat-string">
        <xsl:param name="stringToRepeat" />
        <xsl:param name="count" />
        <xsl:if test="$count > 0">
            <xsl:value-of select="$stringToRepeat"/>
            <xsl:call-template name="repeat-string">
                <xsl:with-param name="stringToRepeat" select="$stringToRepeat"/>
                <xsl:with-param name="count" select="$count - 1"/>
            </xsl:call-template>
        </xsl:if>
    </xsl:template>

    <xsl:template match="/html/body">
        <xsl:apply-templates select="*" />
    </xsl:template>

    <xsl:template match="li">
        <xsl:if test="normalize-space(.) != ''">
            <xsl:call-template name="repeat-string">
                <xsl:with-param name="stringToRepeat" select="'    '"/>
                <xsl:with-param name="count" select="count(ancestor::li)"/>
            </xsl:call-template>
            <xsl:choose>
                <xsl:when test="name(..) = 'ol'">
                    <xsl:value-of select="position()" />
                    <xsl:text>. </xsl:text>
                </xsl:when>
                <xsl:otherwise>
                    <xsl:text>* </xsl:text>
                </xsl:otherwise>
            </xsl:choose>
            <xsl:value-of select="normalize-space(text())" />
            <xsl:apply-templates select="*[not(self::ul) and not(self::ol)]" />
            <xsl:text>&#xa;&#xa;</xsl:text>
            <xsl:apply-templates select="ul|ol" />
        </xsl:if>
    </xsl:template>

    <!-- Don't process text() nodes for these - prevents unnecessary whitespace -->
    <xsl:template match="ul|ol">
        <xsl:apply-templates select="*[not(self::text())]" />
    </xsl:template>

    <xsl:template match="a">
        <xsl:choose>
            <xsl:when test="not(@href) or @href = ''">
                <xsl:value-of select="normalize-space(.)" />
            </xsl:when>
            <xsl:when test="normalize-space(.) = ''">
                <!-- skip -->
            </xsl:when>
            <xsl:otherwise>
                <xsl:text>[</xsl:text>
                <xsl:value-of select="normalize-space(.)" />
                <xsl:text>](</xsl:text>
                <xsl:value-of select="@href" />
                <xsl:text>)</xsl:text>
            </xsl:otherwise>
        </xsl:choose>
    </xsl:template>

    <xsl:template match="img">
        <xsl:text>![</xsl:text>
        <xsl:value-of select="@alt" />
        <xsl:text>](</xsl:text>
        <xsl:value-of select="@src" />
        <xsl:text>)</xsl:text>
    </xsl:template>


    <xsl:template match="strong|b">
        <xsl:text>**</xsl:text>
        <xsl:value-of select="normalize-space(.)" />
        <xsl:text>**</xsl:text>
    </xsl:template>
    <xsl:template match="em|i">
        <xsl:text>*</xsl:text>
        <xsl:value-of select="normalize-space(.)" />
        <xsl:text>*</xsl:text>
    </xsl:template>
    <xsl:template match="del|s|strike">
        <xsl:text>~~</xsl:text>
        <xsl:value-of select="normalize-space(.)" />
        <xsl:text>~~</xsl:text>
    </xsl:template>
    <xsl:template match="code">
        <!-- todo: skip the ` if inside a pre -->
        <xsl:text>`</xsl:text>
        <xsl:value-of select="normalize-space(.)" />
        <xsl:text>`</xsl:text>
    </xsl:template>

    <xsl:template match="br">
        <xsl:text>  &#xa;</xsl:text>
    </xsl:template>

    <!-- Text-level inline elements with no Markdown equivalent: pass through as raw HTML -->
    <xsl:template match="cite|abbr|small|sub|sup|mark|kbd|var|span|time|q|ins|u|tt|bdo|ruby|rp|rt|wbr|font|big|nobr|acronym|basefont|blink|nextid|spacer|listing|marquee|xm">
        <xsl:text>&lt;</xsl:text>
        <xsl:value-of select="name()"/>
        <xsl:apply-templates select="@*" mode="raw-attr"/>
        <xsl:text>&gt;</xsl:text>
        <xsl:apply-templates select="*|text()"/>
        <xsl:text>&lt;/</xsl:text>
        <xsl:value-of select="name()"/>
        <xsl:text>&gt;</xsl:text>
    </xsl:template>

    <!-- Render attributes for raw HTML passthrough -->
    <xsl:template match="@*" mode="raw-attr">
        <xsl:text> </xsl:text>
        <xsl:value-of select="name()"/>
        <xsl:text>="</xsl:text>
        <xsl:value-of select="."/>
        <xsl:text>"</xsl:text>
    </xsl:template>

    <!-- Block elements -->
    <xsl:template match="hr">
        <xsl:text>----&#xa;&#xa;</xsl:text>
    </xsl:template>

    <xsl:template match="p|div">
        <xsl:apply-templates select="*|text()" />
        <xsl:text>&#xa;&#xa;</xsl:text>        <!-- Block element -->
    </xsl:template>

    <xsl:template match="h1|h2|h3|h4|h5|h6">
        <xsl:call-template name="repeat-string">
            <xsl:with-param name="stringToRepeat" select="'#'"/>
            <xsl:with-param name="count" select="number(substring(name(), 2))"/>
        </xsl:call-template>
        <xsl:text></xsl:text>
        <xsl:value-of select="normalize-space(.)" />
        <xsl:text>&#xa;&#xa;</xsl:text>        <!-- Block element -->
    </xsl:template>

    <xsl:template match="pre">
        <xsl:text></xsl:text>
        <xsl:call-template name="replace-newlines">
            <xsl:with-param name="text" select="text()"/>
            <xsl:with-param name="prefix" select="'    '"/>
        </xsl:call-template>
        <xsl:text>&#xa;&#xa;</xsl:text>        <!-- Block element -->
    </xsl:template>

    <xsl:template match="blockquote">
        <xsl:text>&gt;   </xsl:text>
        <xsl:call-template name="replace-newlines">
            <xsl:with-param name="text" select="text()"/>
            <xsl:with-param name="prefix" select="'&gt; '"/>
        </xsl:call-template>
        <xsl:text>&#xa;&#xa;</xsl:text>        <!-- Block element -->
    </xsl:template>

    <!-- Table support (GFM-style) -->
    <xsl:template match="table">
        <xsl:apply-templates select="thead|tbody|tr"/>
        <xsl:text>&#xa;</xsl:text>
    </xsl:template>

    <xsl:template match="thead">
        <xsl:apply-templates select="tr"/>
        <xsl:call-template name="table-separator">
            <xsl:with-param name="cells" select="tr/th|tr/td"/>
        </xsl:call-template>
    </xsl:template>

    <xsl:template match="tbody">
        <xsl:apply-templates select="tr"/>
    </xsl:template>

    <xsl:template match="tr">
        <xsl:text>| </xsl:text>
        <xsl:for-each select="th|td">
            <xsl:value-of select="normalize-space(.)"/>
            <xsl:text> | </xsl:text>
        </xsl:for-each>
        <xsl:text>&#xa;</xsl:text>
    </xsl:template>

    <xsl:template name="table-separator">
        <xsl:param name="cells"/>
        <xsl:text>| </xsl:text>
        <xsl:for-each select="$cells">
            <xsl:choose>
                <xsl:when test="@align = 'left' or @style[contains(., 'text-align: left')]">:---</xsl:when>
                <xsl:when test="@align = 'right' or @style[contains(., 'text-align: right')]">---:</xsl:when>
                <xsl:when test="@align = 'center' or @style[contains(., 'text-align: center')]">:---:</xsl:when>
                <xsl:otherwise>---</xsl:otherwise>
            </xsl:choose>
            <xsl:text> | </xsl:text>
        </xsl:for-each>
        <xsl:text>&#xa;</xsl:text>
    </xsl:template>

    <!-- Helper template to replace newlines with prefix + newline -->
    <xsl:template name="replace-newlines">
        <xsl:param name="text"/>
        <xsl:param name="prefix"/>
        <xsl:choose>
            <xsl:when test="contains($text, '&#xa;')">
                <xsl:value-of select="substring-before($text, '&#xa;')"/>
                <xsl:text>&#xa;</xsl:text>
                <xsl:value-of select="$prefix"/>
                <xsl:call-template name="replace-newlines">
                    <xsl:with-param name="text" select="substring-after($text, '&#xa;')"/>
                    <xsl:with-param name="prefix" select="$prefix"/>
                </xsl:call-template>
            </xsl:when>
            <xsl:otherwise>
                <xsl:value-of select="$text"/>
            </xsl:otherwise>
        </xsl:choose>
    </xsl:template>

    <!-- Convert SEO meta data into text -->
    <xsl:template match="meta[@name or @property][@content]" priority="2">
        <xsl:apply-templates select="@*|text()" />
    </xsl:template>

    <xsl:template match="title">
        <xsl:text># </xsl:text>
        <xsl:value-of select="normalize-space(.)" />
        <xsl:text>&#xa;&#xa;</xsl:text>   
    </xsl:template>

    <xsl:template match="text()">
        <xsl:value-of select="normalize-space(.)" />
    </xsl:template>

    <!-- Ignore these elements and their content -->
    <xsl:template match="menu|script|style|meta|link|head|title|noscript|template" />
</xsl:stylesheet>