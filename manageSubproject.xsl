<xsl:stylesheet
    xmlns:xsl="http://www.w3.org/1999/XSL/Transform" version='1.0'>

   <xsl:include href="footer.xsl"/>
   <xsl:include href="headerback.xsl"/> 
   
    <!-- Local includes -->
   <xsl:include href="local/footer.xsl"/>
   <xsl:include href="local/headerback.xsl"/>  
     
   <xsl:output method="xml" indent="yes"  doctype-public="-//W3C//DTD XHTML 1.0 Transitional//EN" 
   doctype-system="http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd" />

    <xsl:template match="/">
      <html>
       <head>
       <title><xsl:value-of select="cdash/title"/></title>
        <meta name="robots" content="noindex,nofollow" />
         <link rel="StyleSheet" type="text/css">
         <xsl:attribute name="href"><xsl:value-of select="cdash/cssfile"/></xsl:attribute>
         </link>
       </head>
       <body bgcolor="#ffffff">
       
<xsl:choose>         
<xsl:when test="/cdash/uselocaldirectory=1">
  <xsl:call-template name="headerback_local"/>
</xsl:when>
<xsl:otherwise>
  <xsl:call-template name="headerback"/>
</xsl:otherwise>
</xsl:choose>    

<br/>


<xsl:if test="string-length(cdash/warning)>0">
<b>Warning: <xsl:value-of select="cdash/warning"/></b><br/><br/>
</xsl:if>

<table width="100%"  border="0">
  <tr>
    <td width="10%"><div align="right"><strong>Project:</strong></div></td>
    <td width="90%" >
    <form name="form1" method="post">
    <xsl:attribute name="action">manageSubproject.php?projectid=<xsl:value-of select="cdash/project/id"/></xsl:attribute>
    <select onchange="location = 'manageSubproject.php?projectid='+this.options[this.selectedIndex].value;" name="projectSelection">
        <option>
        <xsl:attribute name="value">-1</xsl:attribute>
        Choose...
        </option>
        
        <xsl:for-each select="cdash/availableproject">
        <option>
        <xsl:attribute name="value"><xsl:value-of select="id"/></xsl:attribute>
        <xsl:if test="selected=1">
        <xsl:attribute name="selected"></xsl:attribute>
        </xsl:if>
        <xsl:value-of select="name"/>
        </option>
        </xsl:for-each>
        </select>
      </form>
    </td>
  </tr>
</table> 
  
<!-- If a project has been selected -->
<xsl:if test="cdash/project/id > -1">
<form name="formnewgroup" method="post">
<xsl:attribute name="action">manageSubproject.php?projectid=<xsl:value-of select="cdash/project/id"/></xsl:attribute>
<table width="100%"  border="0">
  <tr>
    <td><div align="right"></div></td>
    <td bgcolor="#DDDDDD"><strong>Current Subprojects</strong></td>
  </tr>
  <xsl:for-each select="cdash/project/subproject">
  <tr>
    <td width="10%"></td>
    <td width="90%">
    <table width="100%" border="0">
    <tr>
    <td width="10%"><xsl:value-of select="name"/></td>
    <td align="left" width="10%"><a><xsl:attribute name="href">manageSubproject.php?projectid=<xsl:value-of select="/cdash/project/id"/>&amp;delete=<xsl:value-of select="id"/>
    </xsl:attribute>
    [x]</a></td>
    <td align="left" width="80%">
    <form method="post">
    <xsl:attribute name="action">manageSubproject.php?projectid=<xsl:value-of select="/cdash/project/id"/></xsl:attribute>
    <xsl:attribute name="name">form_add_dependency_<xsl:value-of select="id"/></xsl:attribute>
    Add dependency: 
    <input type="hidden" name="dependencyid">
    <xsl:attribute name="value"><xsl:value-of select="id"/></xsl:attribute>
    </input>
    <select>
       <xsl:attribute name="name">dependency_selection_<xsl:value-of select="id"/></xsl:attribute>  
        <option>
        <xsl:attribute name="value">-1</xsl:attribute>
        Choose...
        </option>
        
        <xsl:for-each select="availabledependency">
        <option>
        <xsl:attribute name="value"><xsl:value-of select="id"/></xsl:attribute>
        <xsl:if test="selected=1">
        <xsl:attribute name="selected"></xsl:attribute>
        </xsl:if>
        <xsl:value-of select="name"/>
        </option>
        </xsl:for-each>
        </select>
        <input type="submit" name="addDependency" value="Add dependency"/>
      </form>    
    </td>
    </tr>
    <tr> <!-- List the dependencies for that subproject -->
    <td width="100%" colspan="3">
    <xsl:for-each select="dependency">
    - <xsl:value-of select="name"/> <a><xsl:attribute name="href">manageSubproject.php?projectid=<xsl:value-of select="/cdash/project/id"/>&amp;removeDependency=<xsl:value-of select="id"/>&amp;dependency=<xsl:value-of select="../id"/>
    </xsl:attribute>[x]</a>
    <br/>
    </xsl:for-each>
    </td>
    </tr>
    <tr><td width="100%" colspan="3"><hr/></td></tr>
    </table>
    </td>
  </tr>
  </xsl:for-each>
</table> 
</form>
<br/>
<form name="formnewgroup" method="post">
<xsl:attribute name="action">manageSubproject.php?projectid=<xsl:value-of select="cdash/project/id"/></xsl:attribute>
<table width="100%"  border="0">
  <tr>
    <td><div align="right"></div></td>
    <td bgcolor="#DDDDDD"><strong>Add a subproject</strong></td>
  </tr>
  <tr>
    <td width="10%"></td>
    <td width="90%">
    <input name="newsubproject" type="text"></input>
    </td>
  </tr>
  
  <tr>
    <td><div align="right"></div></td>
    <td><input type="submit" name="addSubproject" value="Add subproject >>"/><br/><br/></td>
  </tr>
</table> 
</form>

  
</xsl:if>
 

<br/>

<!-- FOOTER -->
<br/>

<xsl:choose>         
<xsl:when test="/cdash/uselocaldirectory=1">
  <xsl:call-template name="footer_local"/>
</xsl:when>
<xsl:otherwise>
  <xsl:call-template name="footer"/>
</xsl:otherwise>
</xsl:choose>

        </body>
      </html>
    </xsl:template>
</xsl:stylesheet>